<?php

namespace App\Services;

use App\Enums\MilitaryEducationEnrollmentStatus;
use App\Enums\RegistrationStatus;
use App\Enums\Semester;
use App\Enums\Student\ExcuseType;
use App\Enums\Student\StudentStatus;
use App\Enums\Student\StudentWarningType;
use App\Enums\Student\StudyStatus;
use App\Enums\TermOutcome;
use App\Exceptions\TermClosureException;
use App\Models\Level;
use App\Models\Registration;
use App\Models\RegistrationCourse;
use App\Models\Setting;
use App\Models\Student;
use App\Models\StudentExcuse;
use App\Models\StudentTermRecord;
use App\Models\StudentWarning;
use App\Models\TermClosure;
use App\Models\User;
use App\Models\Year;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * End-of-term procedures carried over from chs (AdminController::changeStudentLevel):
 *  - after the first and second terms: academic warnings — a student under the
 *    GPA threshold with fewer than the exempt earned hours gets a warning, any
 *    other student loses one; a student's first term never counts, and the
 *    configured number of active warnings dismisses the student;
 *  - after the summer: promotion by earned hours (the next level's start_hours,
 *    the graduation hours for the last level), otherwise the study status moves
 *    مستجد → باقي → من الخارج with dismissal on the chs rules; graduating also
 *    needs military education where it is required;
 *  - excuses (StudentExcuse): a term excuse or the year's suspension spares the
 *    term's warning; a suspension or both terms excused spares the summer, and
 *    the student goes back to مقيد for the next year.
 * Every run is stored as a TermClosure with one StudentTermRecord per student,
 * so a term is never closed twice and the latest closure can be reverted.
 */
class TermClosureService
{
    /**
     * The rules of the current run, read once from the settings.
     *
     * @var array{threshold: float, exempt_hours: int, dismissal_warnings: int, max_years_in_level: int, graduation_hours: int}
     */
    private array $rules;

    public function __construct(private GpaCalculator $gpaCalculator) {}

    /**
     * The promotion and warning rules from the settings (إعدادات الترقية والإنذارات).
     *
     * @return array{threshold: float, exempt_hours: int, dismissal_warnings: int, max_years_in_level: int, graduation_hours: int}
     */
    public function rules(): array
    {
        return [
            'threshold' => Setting::warningGpaThreshold(),
            'exempt_hours' => Setting::warningExemptEarnedHours(),
            'dismissal_warnings' => Setting::dismissalWarnings(),
            'max_years_in_level' => Setting::maxYearsInLevel(),
            'graduation_hours' => Setting::graduationRequiredHours(),
        ];
    }

    /**
     * Reasons the term cannot be closed yet; empty when it can.
     *
     * @return array<int, string>
     */
    public function blockers(Year $year, Semester $semester): array
    {
        $blockers = [];

        if ($this->closureFor($year, $semester) !== null) {
            $blockers[] = 'تم تنفيذ إجراءات نهاية هذا الترم من قبل.';
        }

        $pendingResults = $this->pendingResultsCount($year, $semester);

        if ($pendingResults > 0) {
            $blockers[] = "مازال هناك {$pendingResults} نتيجة مادة غير مكتملة (Pending) في الترم.";
        }

        if ($earlier = $this->unclosedEarlierTerm($year, $semester)) {
            $blockers[] = "لازم تنفذ إجراءات {$earlier->label()} {$year->year} الأول.";
        }

        if ($semester === Semester::SUMMER) {
            $levels = $this->levels();

            if ($levels->isEmpty()) {
                $blockers[] = 'لا توجد فرق دراسية.';
            }

            $missingHours = $levels->slice(1)->filter(fn (Level $level) => ! $level->start_hours)->pluck('name');

            if ($missingHours->isNotEmpty()) {
                $blockers[] = 'لم يتم تحديد ساعات بداية الفرق: '.$missingHours->implode('، ').' (إعدادات الترقية والإنذارات).';
            }
        }

        return $blockers;
    }

    /**
     * An earlier term of the same year that has registrations but was not closed yet.
     */
    private function unclosedEarlierTerm(Year $year, Semester $semester): ?Semester
    {
        foreach (Semester::cases() as $earlier) {
            if ($earlier->order() >= $semester->order()) {
                break;
            }

            $hasRegistrations = Registration::query()
                ->where('year_id', $year->id)
                ->where('semester', $earlier->value)
                ->whereNotIn('status', [RegistrationStatus::REJECTED->value, RegistrationStatus::CANCELLED->value])
                ->exists();

            if ($hasRegistrations && $this->closureFor($year, $earlier) === null) {
                return $earlier;
            }
        }

        return null;
    }

    public function closureFor(Year $year, Semester $semester): ?TermClosure
    {
        return TermClosure::query()
            ->where('year_id', $year->id)
            ->where('semester', $semester->value)
            ->first();
    }

    /**
     * What closing the term would do to every current student, without saving anything.
     *
     * @return Collection<int, array{student: Student, cgpa: float, earned_hours: int, outcome: TermOutcome, note: string|null, warnings_before: int, warnings_after: int, issue_warning: bool, cleared_warnings: Collection<int, StudentWarning>, level_after: Level|null, study_status_after: StudyStatus|null, status_after: StudentStatus|null}>
     */
    public function simulate(Year $year, Semester $semester): Collection
    {
        $levels = $this->levels();
        $this->rules = $this->rules();

        return $this->currentStudents()
            ->map(fn (Student $student) => $this->decide($student, $year, $semester, $levels))
            ->values();
    }

    /**
     * @throws TermClosureException when the term cannot be closed
     */
    public function close(Year $year, Semester $semester, ?User $user = null): TermClosure
    {
        return DB::transaction(function () use ($year, $semester, $user) {
            Year::whereKey($year->id)->lockForUpdate()->first();

            if ($blockers = $this->blockers($year, $semester)) {
                throw new TermClosureException(implode(' ', $blockers));
            }

            $closure = TermClosure::create([
                'year_id' => $year->id,
                'semester' => $semester->value,
                'closed_by' => $user?->id,
            ]);

            foreach ($this->simulate($year, $semester) as $decision) {
                $this->apply($closure, $decision);
            }

            return $closure;
        });
    }

    /**
     * Restores every student of the closure to their recorded "before" values and
     * removes the closure, so the term can be closed again.
     *
     * @throws TermClosureException when a later term has been closed since
     */
    public function revert(TermClosure $closure): void
    {
        if (TermClosure::query()->where('id', '>', $closure->id)->exists()) {
            throw new TermClosureException('لا يمكن التراجع إلا عن آخر ترم تم تنفيذ إجراءاته.');
        }

        DB::transaction(function () use ($closure) {
            $records = $closure->records()->with(['student' => fn ($query) => $query->withTrashed()])->get();

            foreach ($records as $record) {
                $record->student?->update([
                    'level_id' => $record->level_id_before,
                    'study_status' => $record->study_status_before?->value,
                    'status' => $record->status_before?->value,
                ]);

                if (! empty($record->cleared_warning_ids)) {
                    StudentWarning::whereKey($record->cleared_warning_ids)->update(['is_active' => true]);
                }
            }

            $closure->warnings()->delete();
            $closure->delete();
        });
    }

    /**
     * @param  array{student: Student, cgpa: float, earned_hours: int, outcome: TermOutcome, note: string|null, warnings_before: int, warnings_after: int, issue_warning: bool, cleared_warnings: Collection<int, StudentWarning>, level_after: Level|null, study_status_after: StudyStatus|null, status_after: StudentStatus|null}  $decision
     */
    private function apply(TermClosure $closure, array $decision): void
    {
        $student = $decision['student'];
        $levelBefore = $student->level_id;
        $studyStatusBefore = $student->study_status;
        $statusBefore = $student->status;

        $issuedWarning = null;

        if ($decision['issue_warning']) {
            $issuedWarning = $student->warnings()->create([
                'term_closure_id' => $closure->id,
                'type' => StudentWarningType::WARNING->value,
                'reason' => $decision['note'],
                'is_active' => true,
            ]);
        }

        $clearedIds = $decision['cleared_warnings']->pluck('id')->all();

        if ($clearedIds !== []) {
            StudentWarning::whereKey($clearedIds)->update(['is_active' => false]);
        }

        $student->update([
            'level_id' => $decision['level_after']?->id,
            'study_status' => $decision['study_status_after']?->value,
            'status' => $decision['status_after']?->value,
        ]);

        StudentTermRecord::create([
            'term_closure_id' => $closure->id,
            'student_id' => $student->id,
            'cgpa' => $decision['cgpa'],
            'earned_hours' => $decision['earned_hours'],
            'outcome' => $decision['outcome']->value,
            'note' => $decision['note'],
            'warnings_before' => $decision['warnings_before'],
            'warnings_after' => $decision['warnings_after'],
            'issued_warning_id' => $issuedWarning?->id,
            'cleared_warning_ids' => $clearedIds ?: null,
            'level_id_before' => $levelBefore,
            'level_id_after' => $decision['level_after']?->id,
            'study_status_before' => $studyStatusBefore?->value,
            'study_status_after' => $decision['study_status_after']?->value,
            'status_before' => $statusBefore?->value,
            'status_after' => $decision['status_after']?->value,
        ]);
    }

    /**
     * @param  Collection<int, Level>  $levels
     * @return array{student: Student, cgpa: float, earned_hours: int, outcome: TermOutcome, note: string|null, warnings_before: int, warnings_after: int, issue_warning: bool, cleared_warnings: Collection<int, StudentWarning>, level_after: Level|null, study_status_after: StudyStatus|null, status_after: StudentStatus|null}
     */
    private function decide(Student $student, Year $year, Semester $semester, Collection $levels): array
    {
        $standing = $this->gpaCalculator->cumulative($student, $year, $semester);
        $warnings = $student->warnings;

        $decision = [
            'student' => $student,
            'cgpa' => $standing['gpa'],
            'earned_hours' => $standing['earned_hours'],
            'outcome' => TermOutcome::NO_CHANGE,
            'note' => null,
            'warnings_before' => $warnings->count(),
            'warnings_after' => $warnings->count(),
            'issue_warning' => false,
            'cleared_warnings' => collect(),
            'level_after' => $student->level,
            'study_status_after' => $student->study_status,
            'status_after' => $student->status,
        ];

        if ($this->isExcused($student, $year, $semester)) {
            return [
                ...$decision,
                'outcome' => TermOutcome::EXCUSED,
                'note' => $student->excuses->where('year_id', $year->id)->map(fn (StudentExcuse $excuse) => $excuse->type->label())->unique()->implode('، '),
                'status_after' => $semester === Semester::SUMMER ? StudentStatus::REGISTERED : $decision['status_after'],
            ];
        }

        return $semester === Semester::SUMMER
            ? $this->decidePromotion($decision, $levels)
            : $this->decideWarning($decision, $year, $semester);
    }

    /**
     * @param  array<string, mixed>  $decision
     * @return array<string, mixed>
     */
    private function decideWarning(array $decision, Year $year, Semester $semester): array
    {
        $student = $decision['student'];

        if ($this->isFirstTerm($student, $year, $semester)) {
            return [...$decision, 'outcome' => TermOutcome::FIRST_TERM];
        }

        $threshold = $this->rules['threshold'];
        $dismissalWarnings = $this->rules['dismissal_warnings'];
        $underperforming = $decision['cgpa'] < $threshold
            && $decision['earned_hours'] < $this->rules['exempt_hours'];

        if ($underperforming) {
            $warningsAfter = $decision['warnings_before'] + 1;
            $dismissed = $warningsAfter >= $dismissalWarnings;

            return [
                ...$decision,
                'outcome' => $dismissed ? TermOutcome::DISMISSED : TermOutcome::WARNED,
                'note' => "إنذار أكاديمي ({$warningsAfter} من {$dismissalWarnings}) — {$semester->label()} {$year->year}: "
                    ."المعدل التراكمي {$decision['cgpa']} أقل من {$threshold}",
                'warnings_after' => $warningsAfter,
                'issue_warning' => true,
                'status_after' => $dismissed ? StudentStatus::DISMISSED : $decision['status_after'],
            ];
        }

        if ($decision['warnings_before'] > 0) {
            return [
                ...$decision,
                'outcome' => TermOutcome::WARNING_REDUCED,
                'warnings_after' => $decision['warnings_before'] - 1,
                'cleared_warnings' => $student->warnings->take(1),
            ];
        }

        return $decision;
    }

    /**
     * @param  array<string, mixed>  $decision
     * @param  Collection<int, Level>  $levels
     * @return array<string, mixed>
     */
    private function decidePromotion(array $decision, Collection $levels): array
    {
        $student = $decision['student'];

        $recovered = $decision['cgpa'] >= $this->rules['threshold']
            || $decision['earned_hours'] >= $this->rules['exempt_hours'];

        if ($recovered && $decision['warnings_before'] > 0) {
            $decision['cleared_warnings'] = $student->warnings;
            $decision['warnings_after'] = 0;
        }

        $levelIndex = $levels->search(fn (Level $level) => $level->id === $student->level_id);

        if ($levelIndex === false) {
            return [...$decision, 'note' => 'الفرقة الدراسية للطالب غير معروفة.'];
        }

        $isLastLevel = $levelIndex === $levels->count() - 1;
        $requiredHours = $isLastLevel ? $this->rules['graduation_hours'] : (int) $levels[$levelIndex + 1]->start_hours;

        if ($decision['earned_hours'] < $requiredHours) {
            return $this->holdBack(
                $decision,
                $levels,
                $levelIndex,
                byMilitaryEducation: false,
                note: "الساعات المكتسبة {$decision['earned_hours']} أقل من {$requiredHours}",
            );
        }

        if (! $isLastLevel) {
            return [
                ...$decision,
                'outcome' => TermOutcome::PROMOTED,
                'level_after' => $levels[$levelIndex + 1],
                'study_status_after' => StudyStatus::FRESHMAN,
                'status_after' => StudentStatus::REGISTERED,
            ];
        }

        if (! $this->hasMetMilitaryEducation($student, $levels)) {
            return $this->holdBack($decision, $levels, $levelIndex, byMilitaryEducation: true, note: 'أكمل ساعات التخرج ولم يجتز التربية العسكرية');
        }

        return [...$decision, 'outcome' => TermOutcome::GRADUATED, 'status_after' => StudentStatus::GRADUATED];
    }

    /**
     * A student not promoted moves مستجد → باقي → من الخارج. chs dismisses a باقي student
     * of the first level, a من الخارج student of the second level, and a من الخارج
     * student after the maximum years in one level; only the last rule applies to a
     * student held in the last level for military education.
     *
     * @param  array<string, mixed>  $decision
     * @param  Collection<int, Level>  $levels
     * @return array<string, mixed>
     */
    private function holdBack(array $decision, Collection $levels, int $levelIndex, bool $byMilitaryEducation, string $note): array
    {
        $student = $decision['student'];
        $dismiss = [...$decision, 'outcome' => TermOutcome::DISMISSED, 'note' => $note, 'status_after' => StudentStatus::DISMISSED];
        $stay = fn (StudyStatus $studyStatus) => [
            ...$decision,
            'outcome' => TermOutcome::HELD_BACK,
            'note' => $note,
            'study_status_after' => $studyStatus,
            'status_after' => StudentStatus::REGISTERED,
        ];

        return match ($student->study_status) {
            StudyStatus::REMAINING => ! $byMilitaryEducation && $levelIndex === 0 ? $dismiss : $stay(StudyStatus::EXTERNAL),
            StudyStatus::EXTERNAL => (! $byMilitaryEducation && $levelIndex === 1)
                || $this->yearsInLevel($student, $levels[$levelIndex]) >= $this->rules['max_years_in_level']
                    ? $dismiss
                    : $stay(StudyStatus::EXTERNAL),
            default => $stay(StudyStatus::REMAINING),
        };
    }

    /**
     * chs: a term excuse or the year's suspension spares the term's warning; after the
     * summer, a suspension or both terms excused spares failing the level.
     */
    private function isExcused(Student $student, Year $year, Semester $semester): bool
    {
        $yearExcuses = $student->excuses->where('year_id', $year->id);

        if ($semester !== Semester::SUMMER) {
            return $yearExcuses->contains(fn (StudentExcuse $excuse) => $excuse->excusesTerm($year->id, $semester));
        }

        return $yearExcuses->contains('type', ExcuseType::SUSPENSION)
            || $yearExcuses->where('type', ExcuseType::TERM)->pluck('semester')->unique()->count() >= 2;
    }

    /**
     * A student's first term in the institute never counts for warnings: true when
     * they have no earlier first/second term, registered or closed.
     */
    private function isFirstTerm(Student $student, Year $year, Semester $semester): bool
    {
        $current = $this->termKey($year->year, $semester);

        $hasEarlierRegistration = $student->registrations->contains(fn (Registration $registration) => $registration->semester !== Semester::SUMMER
            && $this->termKey((string) $registration->year?->year, $registration->semester) < $current);

        $hasEarlierClosure = $student->termRecords->contains(fn (StudentTermRecord $record) => ! $record->closure->isPromotion()
            && $this->termKey((string) $record->closure->year?->year, $record->closure->semester) < $current);

        return ! $hasEarlierRegistration && ! $hasEarlierClosure;
    }

    /**
     * Academic years spent in the level, the current one included (chs: registration_years).
     */
    private function yearsInLevel(Student $student, Level $level): int
    {
        return 1 + $student->termRecords
            ->filter(fn (StudentTermRecord $record) => $record->closure->isPromotion() && $record->level_id_before === $level->id)
            ->count();
    }

    /**
     * @param  Collection<int, Level>  $levels
     */
    private function hasMetMilitaryEducation(Student $student, Collection $levels): bool
    {
        $required = match ($student->gender) {
            'male' => $levels->contains('military_required_for_males', true),
            'female' => $levels->contains('military_required_for_females', true),
            default => $levels->contains(fn (Level $level) => $level->military_required_for_males || $level->military_required_for_females),
        };

        return ! $required
            || $student->military_education_passed
            || $student->militaryEducationEnrollments->contains(fn ($enrollment) => $enrollment->status === MilitaryEducationEnrollmentStatus::PASSED);
    }

    private function termKey(string $year, ?Semester $semester): string
    {
        return $year.'|'.($semester?->order() ?? 0);
    }

    /**
     * Levels in study order (by id, as everywhere else in the app).
     *
     * @return Collection<int, Level>
     */
    private function levels(): Collection
    {
        return Level::query()->orderBy('id')->get()->values();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Student>
     */
    private function currentStudents(): \Illuminate\Database\Eloquent\Collection
    {
        return Student::query()
            ->active()
            ->whereNotNull('level_id')
            ->with([
                ...GpaCalculator::relations(),
                'level',
                'excuses',
                'warnings' => fn ($query) => $query->academic()->where('is_active', true)->latest('id'),
                'termRecords.closure.year',
                'militaryEducationEnrollments',
            ])
            ->orderBy('username')
            ->get();
    }

    private function pendingResultsCount(Year $year, Semester $semester): int
    {
        return RegistrationCourse::query()
            ->whereHas('registration', fn (Builder $registration) => $registration
                ->where('year_id', $year->id)
                ->where('semester', $semester->value)
                ->where('status', RegistrationStatus::APPROVED->value))
            ->where(fn (Builder $query) => $query
                ->whereNull('grade_id')
                ->orWhereHas('grade', fn (Builder $grade) => $grade->where('is_pending_default', true)))
            ->count();
    }
}
