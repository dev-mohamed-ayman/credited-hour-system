<?php

namespace App\Services;

use App\Enums\Semester;
use App\Enums\Student\StudentStatus;
use App\Models\Course;
use App\Models\Registration;
use App\Models\Student;
use App\Models\Year;
use App\Support\CourseSemesterMapper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BulkCourseRegistrationService
{
    public function __construct(
        protected CourseEligibilityService $eligibilityService,
        protected CourseRegistrationService $registrationService,
    ) {}

    /**
     * Register every "clean" student in their own level's required courses for the
     * given term. A clean student has no failed (retake), improvement, or overdue
     * courses from earlier terms — anyone else is skipped and must be registered
     * individually. Each student goes through the normal registration path, so the
     * fee gate and wallet charge still apply.
     *
     * @return array{
     *     registered: array<int, array{code: string, name: string, courses: int}>,
     *     skipped: array<int, array{code: string, name: string, reason: string}>
     * }
     */
    public function register(Year $year, Semester $semester, ?int $departmentId = null, ?int $levelId = null): array
    {
        $registered = [];
        $skipped = [];

        foreach ($this->candidateStudents($departmentId, $levelId) as $student) {
            $outcome = $this->registerStudent($student, $year, $semester);

            if ($outcome['success']) {
                $registered[] = ['code' => $student->username, 'name' => $student->name, 'courses' => $outcome['courses']];
            } else {
                $skipped[] = ['code' => $student->username, 'name' => $student->name, 'reason' => $outcome['reason']];
            }
        }

        return ['registered' => $registered, 'skipped' => $skipped];
    }

    /**
     * @return Collection<int, Student>
     */
    private function candidateStudents(?int $departmentId, ?int $levelId): Collection
    {
        return Student::query()
            ->with(['level', 'section'])
            ->whereNotNull('level_id')
            ->whereNotNull('section_id')
            ->where(fn ($query) => $query->whereNull('status')->orWhere('status', StudentStatus::REGISTERED->value))
            ->when($departmentId, fn ($query) => $query->whereHas('section', fn ($section) => $section->where('department_id', $departmentId)))
            ->when($levelId, fn ($query) => $query->where('level_id', $levelId))
            ->orderBy('username')
            ->get();
    }

    /**
     * @return array{success: bool, courses: int, reason: string}
     */
    private function registerStudent(Student $student, Year $year, Semester $semester): array
    {
        $alreadyRegistered = Registration::query()
            ->where('student_id', $student->id)
            ->where('year_id', $year->id)
            ->where('semester', $semester->value)
            ->whereHas('courses')
            ->exists();

        if ($alreadyRegistered) {
            return ['success' => false, 'courses' => 0, 'reason' => 'مسجل بالفعل في هذا الترم.'];
        }

        $buckets = $this->eligibilityService->getBuckets($student, $year, $semester);

        if ($buckets['retake']->isNotEmpty()) {
            return ['success' => false, 'courses' => 0, 'reason' => 'لديه مواد رسوب — يُسجَّل يدوياً.'];
        }

        if ($buckets['improvement']->isNotEmpty()) {
            return ['success' => false, 'courses' => 0, 'reason' => 'لديه مواد تحسين — يُسجَّل يدوياً.'];
        }

        $studentPosition = $this->eligibilityService->curriculumPosition($student->level_id, $semester);

        $hasOverdueCourses = $buckets['due']->contains(
            fn (Course $course) => $this->eligibilityService->curriculumPosition($course->level_id, $course->semester) < $studentPosition
        );

        if ($hasOverdueCourses) {
            return ['success' => false, 'courses' => 0, 'reason' => 'لديه مواد متأخرة من ترم سابق — يُسجَّل يدوياً.'];
        }

        $coreCourseIds = $buckets['due']
            ->filter(fn (Course $course) => $course->level_id === $student->level_id
                && ! $course->is_selected
                && CourseSemesterMapper::sequence($course->semester) === CourseSemesterMapper::sequence($semester))
            ->pluck('id')
            ->all();

        if ($coreCourseIds === []) {
            return ['success' => false, 'courses' => 0, 'reason' => 'لا توجد مواد أساسية متاحة لفرقته في هذا الترم.'];
        }

        DB::beginTransaction();

        try {
            $result = $this->registrationService->save($student, $year, $semester, $coreCourseIds);
        } catch (\Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }

        if (! $result['success']) {
            DB::rollBack();

            return ['success' => false, 'courses' => 0, 'reason' => $result['message']];
        }

        DB::commit();

        return [
            'success' => true,
            'courses' => count($coreCourseIds) - count($result['rejected_course_ids']),
            'reason' => '',
        ];
    }
}
