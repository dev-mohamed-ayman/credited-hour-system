<?php

namespace App\Services;

use App\Enums\RegistrationStatus;
use App\Enums\Semester;
use App\Enums\Student\ExcuseType;
use App\Enums\Student\StudentStatus;
use App\Exceptions\StudentExcuseException;
use App\Models\Grade;
use App\Models\RegistrationCourse;
use App\Models\Student;
use App\Models\StudentExcuse;
use App\Models\TermClosure;
use App\Models\User;
use App\Models\Year;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Excuses carried over from chs (StudentAffairsController::addExcuse):
 *  - عذر عن مادة: the chosen courses of the term become IC;
 *  - عذر عن ترم: every course of the term becomes IC and the term never counts for warnings;
 *  - وقف قيد: recorded in the first term, covers the whole year (no warning, no failing the level).
 * Only courses still Pending can be excused, one excuse per term, never in the summer,
 * and never once the term is closed — the closure reads the excuses.
 */
class StudentExcuseService
{
    /**
     * @param  array<int, int>  $registrationCourseIds  the courses of a course excuse
     *
     * @throws StudentExcuseException
     */
    public function add(Student $student, Year $year, Semester $semester, ExcuseType $type, string $reason, array $registrationCourseIds = [], ?User $user = null): StudentExcuse
    {
        if ($semester === Semester::SUMMER) {
            throw new StudentExcuseException('لا يمكن وضع عذر في الترم الصيفي.');
        }

        if ($type === ExcuseType::SUSPENSION && $semester !== Semester::FIRST) {
            throw new StudentExcuseException('وقف القيد يكون في الترم الأول فقط، ويشمل السنة كلها.');
        }

        $this->ensureTermOpen($year, $semester, 'تم تنفيذ إجراءات نهاية هذا الترم، تراجع عنها الأول علشان تضيف عذر.');

        $yearExcuses = $student->excuses()->where('year_id', $year->id)->get();

        if ($yearExcuses->contains(fn (StudentExcuse $excuse) => $excuse->type === ExcuseType::SUSPENSION || $excuse->semester === $semester)
            || ($type === ExcuseType::SUSPENSION && $yearExcuses->isNotEmpty())) {
            throw new StudentExcuseException('لا يمكن وضع أكثر من عذر للطالب في نفس الترم.');
        }

        $termCourses = $this->termCourses($student, $year, $semester);

        if ($type === ExcuseType::COURSE) {
            $registrationCourseIds = array_values(array_unique(array_map('intval', $registrationCourseIds)));

            if ($registrationCourseIds === []) {
                throw new StudentExcuseException('اختر مادة واحدة على الأقل.');
            }

            $excusedCourses = $termCourses->whereIn('id', $registrationCourseIds);

            if ($excusedCourses->count() !== count($registrationCourseIds)) {
                throw new StudentExcuseException('المواد المختارة مش مسجلة للطالب في الترم ده.');
            }
        } else {
            $excusedCourses = $termCourses;
        }

        $withResult = $excusedCourses->filter(fn (RegistrationCourse $course) => $course->grade !== null && ! $course->grade->is_pending_default);

        if ($withResult->isNotEmpty()) {
            throw new StudentExcuseException('لا يمكن وضع عذر لمادة لها نتيجة: '.$withResult->map(fn (RegistrationCourse $course) => $course->course?->name)->implode('، '));
        }

        $incomplete = Grade::query()->where('name', 'IC')->first();

        if ($incomplete === null && $excusedCourses->isNotEmpty()) {
            throw new StudentExcuseException('تقدير IC مش موجود في سلم التقديرات.');
        }

        return DB::transaction(function () use ($student, $year, $semester, $type, $reason, $user, $excusedCourses, $incomplete) {
            if ($excusedCourses->isNotEmpty()) {
                RegistrationCourse::whereKey($excusedCourses->modelKeys())->update(['grade_id' => $incomplete->id]);
            }

            if ($status = $type->studentStatus()) {
                $student->update(['status' => $status->value]);
            }

            return $student->excuses()->create([
                'year_id' => $year->id,
                'semester' => $semester->value,
                'type' => $type->value,
                'reason' => $reason,
                'excused_course_ids' => $excusedCourses->modelKeys() ?: null,
                'created_by' => $user?->id,
            ]);
        });
    }

    /**
     * Deletes the excuse, puts its courses back to Pending and the student back to مقيد.
     *
     * @throws StudentExcuseException
     */
    public function remove(StudentExcuse $excuse): void
    {
        $this->ensureTermOpen(
            $excuse->year,
            $excuse->semester,
            'تم تنفيذ إجراءات نهاية هذا الترم، لا يمكن حذف العذر.',
            wholeYear: $excuse->type === ExcuseType::SUSPENSION,
        );

        DB::transaction(function () use ($excuse) {
            $incompleteId = Grade::query()->where('name', 'IC')->value('id');
            $pendingId = Grade::pendingDefault()?->id;

            if (! empty($excuse->excused_course_ids) && $incompleteId !== null) {
                RegistrationCourse::whereKey($excuse->excused_course_ids)
                    ->where('grade_id', $incompleteId)
                    ->update(['grade_id' => $pendingId]);
            }

            $student = $excuse->student;
            $status = $excuse->type->studentStatus();

            if ($status !== null && $student?->status === $status) {
                $student->update(['status' => StudentStatus::REGISTERED->value]);
            }

            $excuse->delete();
        });
    }

    /**
     * The student's courses in the term (rejected and cancelled registrations aside).
     *
     * @return Collection<int, RegistrationCourse>
     */
    public function termCourses(Student $student, Year $year, Semester $semester): Collection
    {
        return RegistrationCourse::query()
            ->whereHas('registration', fn (Builder $registration) => $registration
                ->where('student_id', $student->id)
                ->where('year_id', $year->id)
                ->where('semester', $semester->value)
                ->whereNotIn('status', [RegistrationStatus::REJECTED->value, RegistrationStatus::CANCELLED->value]))
            ->with(['course', 'grade'])
            ->get();
    }

    /**
     * @throws StudentExcuseException
     */
    private function ensureTermOpen(Year $year, Semester $semester, string $message, bool $wholeYear = false): void
    {
        if (TermClosure::query()->where('year_id', $year->id)->unless($wholeYear, fn ($query) => $query->where('semester', $semester->value))->exists()) {
            throw new StudentExcuseException($message);
        }
    }
}
