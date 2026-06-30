<?php

namespace App\Services;

use App\Enums\Semester;
use App\Models\Course;
use App\Models\FailingGradeSetting;
use App\Models\Grade;
use App\Models\ImprovementGradeSetting;
use App\Models\Registration;
use App\Models\RegistrationCourse;
use App\Models\Setting;
use App\Models\Student;
use App\Models\Year;
use App\Support\CourseSemesterMapper;
use Illuminate\Support\Collection;

class CourseEligibilityService
{
    /** @var Collection<int, int>|null */
    private ?Collection $failingGradeIds = null;

    /** @var Collection<int, int>|null */
    private ?Collection $improvementGradeIds = null;

    private ?int $pendingGradeId = null;

    /**
     * @return array{
     *     retake: Collection<int, Course>,
     *     improvement: Collection<int, Course>,
     *     due: Collection<int, Course>
     * }
     */
    public function getBuckets(
        Student $student,
        Year $year,
        Semester $registrationSemester,
        ?Registration $currentRegistration = null,
        bool $isHistorical = false
    ): array {
        $student->loadMissing(['level', 'section']);

        $historicalSequence = $isHistorical
            ? ($year->id * 100) + CourseSemesterMapper::sequence($registrationSemester)
            : null;

        $attempts = $this->getStudentAttempts($student, $historicalSequence);
        $registeredInSessionIds = $currentRegistration
            ? $currentRegistration->courses()->pluck('course_id')
            : collect();

        $allowCrossLevel = $this->isCrossLevelRegistrationAllowed();
        $attemptedCourseIds = $attempts->pluck('course_id')->unique();

        $candidateCourses = Course::query()
            ->with(['prerequisites', 'level', 'sections'])
            ->where('is_active', true)
            ->when(
                $student->section?->department_id,
                fn ($query, $departmentId) => $query->where('department_id', $departmentId)
            )
            ->where(function ($query) use ($student, $allowCrossLevel, $attemptedCourseIds) {
                if ($attemptedCourseIds->isNotEmpty()) {
                    $query->whereIn('id', $attemptedCourseIds);
                }

                $query->orWhere(function ($levelQuery) use ($student, $allowCrossLevel) {
                    if ($allowCrossLevel) {
                        if ($student->section_id) {
                            $levelQuery->whereHas(
                                'sections',
                                fn ($sectionQuery) => $sectionQuery->where('sections.id', $student->section_id)
                            );
                        }
                    } elseif ($student->level_id) {
                        $levelQuery->where('level_id', $student->level_id);
                    }
                });
            })
            ->get();

        $retake = collect();
        $improvement = collect();
        $due = collect();

        foreach ($candidateCourses as $course) {
            if ($registeredInSessionIds->contains($course->id)) {
                continue;
            }

            if ($this->isRetakeBucket($course, $attempts)) {
                $retake->push($course);

                continue;
            }

            if ($this->isImprovementBucket($course, $attempts)) {
                $improvement->push($course);

                continue;
            }

            if ($this->isDueBucket($course, $student, $registrationSemester, $attempts)) {
                $due->push($course);
            }
        }

        return [
            'retake' => $retake->sortBy('name')->values(),
            'improvement' => $improvement->sortBy('name')->values(),
            'due' => $due->sortBy('name')->values(),
        ];
    }

    public function hasSatisfactoryAttempt(Course $course, Collection $attempts): bool
    {
        return $attempts
            ->where('course_id', $course->id)
            ->contains(fn (RegistrationCourse $attempt) => $this->isSatisfactoryGrade($attempt->grade_id));
    }

    public function latestAttemptGrade(Course $course, Collection $attempts): ?int
    {
        $latest = $this->getOrderedAttempts($attempts)
            ->where('course_id', $course->id)
            ->first();

        return $latest?->grade_id;
    }

    public function isRetakeBucket(Course $course, Collection $attempts): bool
    {
        $courseAttempts = $attempts->where('course_id', $course->id);

        if ($courseAttempts->isEmpty()) {
            return false;
        }

        if ($this->hasSatisfactoryAttempt($course, $attempts)) {
            return false;
        }

        $latestGradeId = $this->latestAttemptGrade($course, $attempts);

        return $latestGradeId !== null && $this->getFailingGradeIds()->contains($latestGradeId);
    }

    public function isImprovementBucket(Course $course, Collection $attempts): bool
    {
        if (! $this->hasSatisfactoryAttempt($course, $attempts)) {
            return false;
        }

        $satisfactoryAttempt = $this->getOrderedAttempts($attempts)
            ->where('course_id', $course->id)
            ->first(fn (RegistrationCourse $attempt) => $this->isSatisfactoryGrade($attempt->grade_id));

        if (! $satisfactoryAttempt) {
            return false;
        }

        return $this->getImprovementGradeIds()->contains($satisfactoryAttempt->grade_id);
    }

    public function isDueBucket(
        Course $course,
        Student $student,
        Semester $registrationSemester,
        Collection $attempts
    ): bool {
        if ($attempts->where('course_id', $course->id)->isNotEmpty()) {
            return false;
        }

        if (! $course->is_active) {
            return false;
        }

        if ($student->section?->department_id && $course->department_id !== $student->section->department_id) {
            return false;
        }

        if (! $this->matchesLevelRegistrationRules($course, $student)) {
            return false;
        }

        if (! $this->isCurriculumWithinCutoff($course, $student, $registrationSemester)) {
            return false;
        }

        foreach ($course->prerequisites as $prerequisite) {
            if (! $this->hasSatisfactoryAttempt($prerequisite, $attempts)) {
                return false;
            }
        }

        return true;
    }

    public function isCrossLevelRegistrationAllowed(): bool
    {
        return Setting::allowCrossLevelRegistration();
    }

    public function matchesLevelRegistrationRules(Course $course, Student $student): bool
    {
        if ($this->isCrossLevelRegistrationAllowed()) {
            if (! $student->section_id) {
                return false;
            }

            $course->loadMissing('sections');

            return $course->sections->contains('id', $student->section_id);
        }

        if (! $student->level_id || ! $course->level_id) {
            return false;
        }

        return $course->level_id === $student->level_id;
    }

    public function isCurriculumWithinCutoff(Course $course, Student $student, Semester $registrationSemester): bool
    {
        if (! $course->level_id || ! $student->level_id) {
            return false;
        }

        $coursePosition = $this->curriculumPosition($course->level_id, $course->semester);
        $studentPosition = $this->curriculumPosition($student->level_id, $registrationSemester);

        return $coursePosition <= $studentPosition;
    }

    public function curriculumPosition(int $levelId, Semester|string $semester): int
    {
        $semesterSequence = CourseSemesterMapper::sequence($semester);

        return ($levelId * 10) + $semesterSequence;
    }

    public function isSatisfactoryGrade(int $gradeId): bool
    {
        if ($gradeId === $this->getPendingGradeId()) {
            return false;
        }

        return ! $this->getFailingGradeIds()->contains($gradeId);
    }

    /**
     * @return Collection<int, RegistrationCourse>
     */
    public function getStudentAttempts(Student $student, ?int $upToHistoricalSequence = null): Collection
    {
        $attempts = RegistrationCourse::query()
            ->with(['registration.year', 'grade'])
            ->whereHas('registration', fn ($query) => $query->where('student_id', $student->id))
            ->get();

        if ($upToHistoricalSequence !== null) {
            $attempts = $attempts->filter(function (RegistrationCourse $attempt) use ($upToHistoricalSequence) {
                $attemptSequence = ($attempt->registration->year_id * 100) + CourseSemesterMapper::sequence($attempt->registration->semester);

                return $attemptSequence < $upToHistoricalSequence;
            });
        }

        return $attempts->values();
    }

    /**
     * @param  Collection<int, RegistrationCourse>  $attempts
     * @return Collection<int, RegistrationCourse>
     */
    public function getOrderedAttempts(Collection $attempts): Collection
    {
        return $attempts
            ->sortByDesc(function (RegistrationCourse $attempt) {
                $registration = $attempt->registration;
                $semesterSequence = CourseSemesterMapper::sequence($registration->semester);

                return ($registration->year_id * 100) + $semesterSequence;
            })
            ->values();
    }

    /**
     * @return Collection<int, int>
     */
    public function getFailingGradeIds(): Collection
    {
        if ($this->failingGradeIds === null) {
            $this->failingGradeIds = FailingGradeSetting::query()->pluck('grade_id');
        }

        return $this->failingGradeIds;
    }

    /**
     * @return Collection<int, int>
     */
    public function getImprovementGradeIds(): Collection
    {
        if ($this->improvementGradeIds === null) {
            $this->improvementGradeIds = ImprovementGradeSetting::query()->pluck('grade_id');
        }

        return $this->improvementGradeIds;
    }

    public function getPendingGradeId(): ?int
    {
        if ($this->pendingGradeId === null) {
            $this->pendingGradeId = Grade::query()->where('is_pending_default', true)->value('id');
        }

        return $this->pendingGradeId;
    }

    public function resetCache(): void
    {
        $this->failingGradeIds = null;
        $this->improvementGradeIds = null;
        $this->pendingGradeId = null;
    }

    /**
     * Validates if a registration course can be deleted based on historical prerequisites.
     *
     * @return array{allowed: bool, blocking_courses: array<string>}
     */
    public function canDeleteRegistrationCourse(RegistrationCourse $registrationCourse): array
    {
        $course = $registrationCourse->course;
        $gradeId = $registrationCourse->grade_id;
        $registration = $registrationCourse->registration;

        // 1. Is grade successful? If not, deletion is always allowed.
        if (! $this->isSatisfactoryGrade($gradeId)) {
            return ['allowed' => true, 'blocking_courses' => []];
        }

        $student = $registration->student;
        $attempts = $this->getStudentAttempts($student);

        $currentSequence = ($registration->year_id * 100) + CourseSemesterMapper::sequence($registration->semester);

        // 2. Find all attempts in subsequent registrations
        $laterAttempts = $attempts->filter(function (RegistrationCourse $attempt) use ($currentSequence) {
            $attemptSequence = ($attempt->registration->year_id * 100) + CourseSemesterMapper::sequence($attempt->registration->semester);

            return $attemptSequence > $currentSequence;
        });

        $blockingCourses = [];

        // 3. Check if any subsequent course requires this course as a prerequisite
        foreach ($laterAttempts as $laterAttempt) {
            $laterCourse = $laterAttempt->course;

            if ($laterCourse->prerequisites()->where('prerequisite_course_id', $course->id)->exists()) {
                $blockingCourses[] = $laterCourse->name;
            }
        }

        if (count($blockingCourses) > 0) {
            return [
                'allowed' => false,
                'blocking_courses' => array_values(array_unique($blockingCourses)),
            ];
        }

        return ['allowed' => true, 'blocking_courses' => []];
    }
}
