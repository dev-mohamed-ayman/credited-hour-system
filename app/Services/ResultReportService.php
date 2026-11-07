<?php

namespace App\Services;

use App\Enums\RegistrationStatus;
use App\Enums\Semester;
use App\Models\Course;
use App\Models\Grade;
use App\Models\RegistrationCourse;
use App\Models\Student;
use App\Models\Year;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Control reports: courses still waiting for results, the GPA ranking, and the
 * grade distribution of each course in a term.
 */
class ResultReportService
{
    public function __construct(private GpaCalculator $gpaCalculator) {}

    /**
     * Courses of the term with approved students still Pending, most pending first.
     *
     * @return Collection<int, array{course: Course, enrolled: int, pending: int}>
     */
    public function pendingCourses(Year $year, Semester $semester): Collection
    {
        return $this->termEnrollments($year, $semester)
            ->with(['course', 'grade'])
            ->get()
            ->groupBy('course_id')
            ->map(fn (Collection $enrollments) => [
                'course' => $enrollments->first()->course,
                'enrolled' => $enrollments->count(),
                'pending' => $enrollments->filter(fn (RegistrationCourse $enrollment) => $enrollment->grade === null || $enrollment->grade->is_pending_default)->count(),
            ])
            ->filter(fn (array $row) => $row['pending'] > 0 && $row['course'] !== null)
            ->sortByDesc('pending')
            ->values();
    }

    /**
     * Current students ranked by cumulative GPA (earned hours break ties).
     *
     * @return Collection<int, array{rank: int, student: Student, gpa: float, earned_hours: int, total_hours: int, classification: string}>
     */
    public function ranking(?int $departmentId, ?int $levelId, int $limit = 0): Collection
    {
        $ranked = Student::query()
            ->active()
            ->when($departmentId, fn (Builder $query) => $query->whereHas('section', fn (Builder $section) => $section->where('department_id', $departmentId)))
            ->when($levelId, fn (Builder $query) => $query->where('level_id', $levelId))
            ->with([...GpaCalculator::relations(), 'level', 'section.department'])
            ->get()
            ->map(function (Student $student) {
                $standing = $this->gpaCalculator->cumulative($student);

                return [
                    'student' => $student,
                    'gpa' => $standing['gpa'],
                    'earned_hours' => $standing['earned_hours'],
                    'total_hours' => $standing['total_hours'],
                    'classification' => $standing['total_hours'] > 0 ? $this->gpaCalculator->classification($standing['gpa']) : '',
                ];
            })
            ->filter(fn (array $row) => $row['total_hours'] > 0)
            ->sort(fn (array $a, array $b) => [$b['gpa'], $b['earned_hours']] <=> [$a['gpa'], $a['earned_hours']])
            ->values()
            ->map(fn (array $row, int $index) => ['rank' => $index + 1, ...$row]);

        return $limit > 0 ? $ranked->take($limit) : $ranked;
    }

    /**
     * How many students got each grade in every course of the term.
     *
     * @return array{grades: Collection<int, Grade>, courses: Collection<int, array{course: Course, total: int, counts: array<int, int>}>}
     */
    public function gradeDistribution(Year $year, Semester $semester, ?int $departmentId = null): array
    {
        $grades = Grade::query()->where('is_pending_default', false)->orderBy('order')->get();

        $courses = $this->termEnrollments($year, $semester)
            ->when($departmentId, fn (Builder $query) => $query->whereHas('course', fn (Builder $course) => $course->where('department_id', $departmentId)))
            ->with('course')
            ->get()
            ->groupBy('course_id')
            ->map(fn (Collection $enrollments) => [
                'course' => $enrollments->first()->course,
                'total' => $enrollments->count(),
                'counts' => $enrollments->countBy('grade_id')->all(),
            ])
            ->filter(fn (array $row) => $row['course'] !== null)
            ->sortBy(fn (array $row) => $row['course']->code)
            ->values();

        return ['grades' => $grades, 'courses' => $courses];
    }

    private function termEnrollments(Year $year, Semester $semester): Builder
    {
        return RegistrationCourse::query()
            ->whereHas('registration', fn (Builder $registration) => $registration
                ->where('year_id', $year->id)
                ->where('semester', $semester->value)
                ->where('status', RegistrationStatus::APPROVED->value)
                ->whereHas('student'));
    }
}
