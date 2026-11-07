<?php

namespace App\Services;

use App\Enums\MilitaryEducationEnrollmentStatus;
use App\Enums\RegistrationStatus;
use App\Enums\Semester;
use App\Models\Level;
use App\Models\Registration;
use App\Models\RegistrationCourse;
use App\Models\Student;
use App\Models\StudentTermNote;
use App\Models\StudentTermRecord;
use App\Models\Year;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The control's grade sheet (chs: printStudentsGrade / printSummerGrade): one page per
 * student for an academic year, every term with its courses, marks, grades and points,
 * the term GPA and hours, the cumulative GPA at the end of the term, and the term notes.
 */
class GradeSheetService
{
    public function __construct(private GpaCalculator $gpaCalculator) {}

    /**
     * @param  Semester|null  $semester  one term only (e.g. the summer sheet), or the whole year
     * @return Collection<int, array{student: Student, level: Level|null, terms: array<int, array<string, mixed>>, cumulative: array{gpa: float, total_hours: int, earned_hours: int, points: float}, classification: string, military_education: string}>
     */
    public function pages(Year $year, ?Semester $semester, ?int $departmentId, ?int $levelId, ?string $studentCode = null): Collection
    {
        $semesters = $semester ? [$semester] : Semester::cases();

        return Student::query()
            ->whereHas('registrations', fn (Builder $registration) => $registration
                ->where('year_id', $year->id)
                ->whereIn('semester', array_map(fn (Semester $term) => $term->value, $semesters))
                ->whereNotIn('status', [RegistrationStatus::REJECTED->value, RegistrationStatus::CANCELLED->value]))
            ->when($studentCode, fn (Builder $query) => $query->where('username', trim((string) $studentCode)))
            ->when($departmentId, fn (Builder $query) => $query->whereHas('section', fn (Builder $section) => $section->where('department_id', $departmentId)))
            ->with([
                ...GpaCalculator::relations(),
                'section.department',
                'level',
                'termNotes',
                'termRecords.closure',
                'termRecords.levelBefore',
                'militaryEducationEnrollments',
            ])
            ->orderBy('username')
            ->get()
            ->map(fn (Student $student) => ['student' => $student, 'level' => $this->levelInYear($student, $year)])
            ->filter(fn (array $page) => ! $levelId || $page['level']?->id === $levelId)
            ->map(fn (array $page) => $this->page($page['student'], $page['level'], $year, $semesters))
            ->values();
    }

    /**
     * @param  array<int, Semester>  $semesters
     * @return array<string, mixed>
     */
    private function page(Student $student, ?Level $level, Year $year, array $semesters): array
    {
        $terms = [];

        foreach ($semesters as $term) {
            $registration = $student->registrations->first(fn (Registration $registration) => (int) $registration->year_id === $year->id && $registration->semester === $term);

            if ($registration === null) {
                continue;
            }

            $terms[] = [
                'semester' => $term,
                'courses' => $registration->courses->map(fn (RegistrationCourse $enrollment) => [
                    'code' => $enrollment->course?->code,
                    'name' => $enrollment->course?->name,
                    'hours' => (int) $enrollment->course?->hours,
                    'written' => $enrollment->written_score ?? $enrollment->exam_status?->label(),
                    'coursework' => $enrollment->coursework_score,
                    'total' => $enrollment->total_score ?? $enrollment->exam_status?->label(),
                    'grade' => $enrollment->grade?->name,
                    'points' => $enrollment->grade?->countsInGpa() ? (float) $enrollment->grade->points : null,
                ])->values()->all(),
                'standing' => $this->gpaCalculator->forCourses($registration->courses),
                'registered_hours' => $registration->courses->sum(fn (RegistrationCourse $enrollment) => (int) $enrollment->course?->hours),
                'cumulative' => $this->gpaCalculator->cumulative($student, $year, $term),
                'note' => $student->termNotes->first(fn (StudentTermNote $note) => (int) $note->year_id === $year->id && $note->semester === $term)?->note,
            ];
        }

        $cumulative = $this->gpaCalculator->cumulative($student, $year, end($semesters));

        return [
            'student' => $student,
            'level' => $level,
            'terms' => $terms,
            'cumulative' => $cumulative,
            'classification' => $cumulative['total_hours'] > 0 ? $this->gpaCalculator->classification($cumulative['gpa']) : '',
            'military_education' => $student->military_education_passed
                || $student->militaryEducationEnrollments->contains(fn ($enrollment) => $enrollment->status === MilitaryEducationEnrollmentStatus::PASSED)
                    ? 'مجتاز' : 'غير مجتاز',
        ];
    }

    /**
     * The level the student studied in during the year: the level their first closure
     * of that year started from, otherwise their current level.
     */
    private function levelInYear(Student $student, Year $year): ?Level
    {
        $record = $student->termRecords
            ->filter(fn (StudentTermRecord $record) => (int) $record->closure?->year_id === $year->id)
            ->sortBy('id')
            ->first();

        return $record?->levelBefore ?? $student->level;
    }
}
