<?php

namespace App\Services;

use App\Enums\RegistrationStatus;
use App\Enums\Semester;
use App\Models\Grade;
use App\Models\RegistrationCourse;
use App\Models\Student;
use App\Models\TransferEquivalency;
use Illuminate\Support\Collection;

/**
 * GPA rules carried over from the legacy system:
 *  - grade points × course hours, divided by the hours that count;
 *  - Pending, IC and W never count, F and FX count with zero points;
 *  - a repeated course counts once, by its latest counted attempt
 *    (transfer equivalencies come before any registration);
 *  - earned hours are the counted hours carrying points above zero.
 */
class GpaCalculator
{
    /**
     * @return array{gpa: float, total_hours: int, earned_hours: int, points: float}
     */
    public function cumulative(Student $student): array
    {
        $student->loadMissing([
            'transferEquivalencies' => fn ($query) => $query->with(['course', 'grade']),
            'registrations' => fn ($query) => $query
                ->whereNotIn('status', [RegistrationStatus::REJECTED->value, RegistrationStatus::CANCELLED->value])
                ->with(['year', 'courses.course', 'courses.grade']),
        ]);

        $attempts = $student->transferEquivalencies
            ->map(fn (TransferEquivalency $equivalency) => [
                'course_id' => $equivalency->course_id,
                'hours' => (int) $equivalency->course?->hours,
                'grade' => $equivalency->grade,
                'sort' => '0',
            ]);

        foreach ($student->registrations as $registration) {
            if (in_array($registration->status, [RegistrationStatus::REJECTED, RegistrationStatus::CANCELLED], true)) {
                continue;
            }

            $sort = '1|'.$registration->year?->year.'|'.$this->semesterRank($registration->semester);

            foreach ($registration->courses as $registrationCourse) {
                $attempts->push([
                    'course_id' => $registrationCourse->course_id,
                    'hours' => (int) $registrationCourse->course?->hours,
                    'grade' => $registrationCourse->grade,
                    'sort' => $sort,
                ]);
            }
        }

        $latestCountedAttempts = $attempts
            ->filter(fn (array $attempt) => $attempt['grade']?->countsInGpa())
            ->sortBy('sort', SORT_STRING)
            ->groupBy('course_id')
            ->map(fn (Collection $courseAttempts) => $courseAttempts->last());

        return $this->summarize($latestCountedAttempts);
    }

    /**
     * GPA of a single term: every counted course of that registration, no de-duplication.
     *
     * @param  iterable<RegistrationCourse>  $registrationCourses
     * @return array{gpa: float, total_hours: int, earned_hours: int, points: float}
     */
    public function forCourses(iterable $registrationCourses): array
    {
        $attempts = collect($registrationCourses)
            ->filter(fn (RegistrationCourse $registrationCourse) => $registrationCourse->grade?->countsInGpa())
            ->map(fn (RegistrationCourse $registrationCourse) => [
                'hours' => (int) $registrationCourse->course?->hours,
                'grade' => $registrationCourse->grade,
            ]);

        return $this->summarize($attempts);
    }

    /**
     * @param  Collection<int, array{hours: int, grade: Grade}>  $attempts
     * @return array{gpa: float, total_hours: int, earned_hours: int, points: float}
     */
    private function summarize(Collection $attempts): array
    {
        $points = 0.0;
        $totalHours = 0;
        $earnedHours = 0;

        foreach ($attempts as $attempt) {
            $gradePoints = (float) $attempt['grade']->points;

            $points += $attempt['hours'] * $gradePoints;
            $totalHours += $attempt['hours'];

            if ($gradePoints > 0) {
                $earnedHours += $attempt['hours'];
            }
        }

        return [
            'gpa' => $totalHours > 0 ? round($points / $totalHours, 2) : 0.0,
            'total_hours' => $totalHours,
            'earned_hours' => $earnedHours,
            'points' => round($points, 2),
        ];
    }

    private function semesterRank(?Semester $semester): int
    {
        return match ($semester) {
            Semester::FIRST => 1,
            Semester::SECOND => 2,
            Semester::SUMMER => 3,
            default => 0,
        };
    }
}
