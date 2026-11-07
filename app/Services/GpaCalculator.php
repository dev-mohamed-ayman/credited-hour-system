<?php

namespace App\Services;

use App\Enums\RegistrationStatus;
use App\Enums\Semester;
use App\Models\Grade;
use App\Models\RegistrationCourse;
use App\Models\ResultPublication;
use App\Models\Student;
use App\Models\TransferEquivalency;
use App\Models\Year;
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
     * Student relations cumulative() reads — eager load them when computing many students.
     *
     * @return array<string, \Closure>
     */
    public static function relations(): array
    {
        return [
            'transferEquivalencies' => fn ($query) => $query->with(['course', 'grade']),
            'registrations' => fn ($query) => $query
                ->whereNotIn('status', [RegistrationStatus::REJECTED->value, RegistrationStatus::CANCELLED->value])
                ->with(['year', 'courses.course', 'courses.grade']),
        ];
    }

    /**
     * @param  Year|null  $untilYear  with $untilSemester: the standing at the end of that term
     * @param  bool  $publishedOnly  leave out terms whose results are not announced yet (what the student sees)
     * @return array{gpa: float, total_hours: int, earned_hours: int, points: float}
     */
    public function cumulative(Student $student, ?Year $untilYear = null, ?Semester $untilSemester = null, bool $publishedOnly = false): array
    {
        $student->loadMissing(self::relations());

        $until = $untilYear && $untilSemester ? $untilYear->year.'|'.$untilSemester->order() : null;
        $published = $publishedOnly ? ResultPublication::publishedTermKeys() : null;

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

            $term = $registration->year?->year.'|'.($registration->semester?->order() ?? 0);

            if ($until !== null && strcmp($term, $until) > 0) {
                continue;
            }

            if ($published !== null && ! in_array($registration->year_id.'|'.$registration->semester?->value, $published, true)) {
                continue;
            }

            $sort = '1|'.$term;

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
     * The overall classification (التقدير العام) of a cumulative GPA, from config('grading.classifications').
     */
    public function classification(float $gpa): string
    {
        $band = collect(config('grading.classifications', []))
            ->sortByDesc('min')
            ->first(fn (array $band) => $gpa >= (float) $band['min']);

        return $band['label'] ?? '';
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
}
