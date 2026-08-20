<?php

namespace App\Livewire\Student\CourseRegistration;

use App\Enums\Semester;
use App\Models\Course;
use App\Models\Registration;
use App\Models\Student;
use App\Models\Year;
use App\Services\CourseEligibilityService;
use App\Services\CourseRegistrationService;
use App\Services\RegistrationBillingService;
use Illuminate\Support\Collection;
use Livewire\Component;

class Index extends Component
{
    public ?Student $student = null;

    /** @var array<int> */
    public array $selectedRetake = [];

    /** @var array<int> */
    public array $selectedImprovement = [];

    /** @var array<int> */
    public array $selectedDue = [];

    public Collection $retakeCourses;

    public Collection $improvementCourses;

    public Collection $dueCourses;

    public ?Semester $currentSemester = null;

    public ?Year $currentYear = null;

    public bool $registrationAvailable = true;

    public ?int $maxOptionalCourses = null;

    public function mount(): void
    {
        $this->retakeCourses = collect();
        $this->improvementCourses = collect();
        $this->dueCourses = collect();
        $this->currentYear = Year::current();
        $this->currentSemester = Year::currentSemester();
        $this->student = auth('student')->user();

        if ($this->currentSemester === Semester::SUMMER) {
            $this->registrationAvailable = false;
        }

        if ($this->student && $this->registrationAvailable && $this->currentYear && $this->currentSemester) {
            if (in_array($this->currentSemester, [Semester::FIRST, Semester::SECOND], true)) {
                $this->loadBuckets();
            }
        }
    }

    public function updatedSelectedRetake(): void
    {
        $this->enforceOptionalLimit();
    }

    public function updatedSelectedImprovement(): void
    {
        $this->enforceOptionalLimit();
    }

    public function updatedSelectedDue(): void
    {
        $this->enforceOptionalLimit();
    }

    public function save(CourseRegistrationService $registrationService): void
    {
        if (! $this->student || ! $this->currentYear || ! $this->currentSemester) {
            $this->dispatch('toast', ['type' => 'error', 'message' => 'حدث خطأ.']);

            return;
        }

        if (! $this->registrationAvailable) {
            $this->dispatch('toast', ['type' => 'error', 'message' => 'لا يوجد تسجيل مواد في الترم الصيفي.']);

            return;
        }

        $courseIds = array_values(array_unique(array_merge(
            $this->selectedRetake,
            $this->selectedImprovement,
            $this->selectedDue
        )));

        $result = $registrationService->save(
            $this->student,
            $this->currentYear,
            $this->currentSemester,
            $courseIds
        );

        $this->dispatch('toast', [
            'type' => $result['success'] ? 'success' : 'error',
            'message' => $result['message'],
        ]);

        if ($result['success']) {
            $this->resetSelections();
            $this->loadBuckets();
        }
    }

    public function getSelectedOptionalCountProperty(): int
    {
        $allSelectedIds = array_merge($this->selectedRetake, $this->selectedImprovement, $this->selectedDue);

        if (empty($allSelectedIds)) {
            return 0;
        }

        return Course::query()
            ->whereIn('id', $allSelectedIds)
            ->where('is_selected', true)
            ->count();
    }

    public function isOptionalDisabled(int $courseId): bool
    {
        if ($this->maxOptionalCourses === null) {
            return false;
        }

        $course = $this->findCourseInBuckets($courseId);

        if (! $course || ! $course->is_selected) {
            return false;
        }

        $isSelected = in_array($courseId, array_merge(
            $this->selectedRetake,
            $this->selectedImprovement,
            $this->selectedDue
        ), true);

        if ($isSelected) {
            return false;
        }

        return $this->selectedOptionalCount >= $this->maxOptionalCourses;
    }

    protected function loadBuckets(): void
    {
        if (! $this->student || ! $this->currentYear || ! $this->currentSemester) {
            return;
        }

        $registration = Registration::query()
            ->where('student_id', $this->student->id)
            ->where('year_id', $this->currentYear->id)
            ->where('semester', $this->currentSemester->value)
            ->first();

        $eligibilityService = app(CourseEligibilityService::class);
        $buckets = $eligibilityService->getBuckets(
            $this->student,
            $this->currentYear,
            $this->currentSemester,
            $registration
        );

        $this->retakeCourses = $buckets['retake'];
        $this->improvementCourses = $buckets['improvement'];
        $this->dueCourses = $buckets['due'];

        $registrationService = app(CourseRegistrationService::class);
        $this->maxOptionalCourses = $registrationService->getMaxOptionalCourses(
            $this->student,
            $this->currentSemester
        );
    }

    protected function resetSelections(): void
    {
        $this->selectedRetake = [];
        $this->selectedImprovement = [];
        $this->selectedDue = [];
    }

    protected function enforceOptionalLimit(): void
    {
        if ($this->maxOptionalCourses === null) {
            return;
        }

        $optionalCourses = Course::query()
            ->whereIn('id', array_merge($this->selectedRetake, $this->selectedImprovement, $this->selectedDue))
            ->where('is_selected', true)
            ->orderBy('id')
            ->get();

        if ($optionalCourses->count() <= $this->maxOptionalCourses) {
            return;
        }

        $excess = $optionalCourses->slice($this->maxOptionalCourses);

        foreach ($excess as $course) {
            $this->selectedRetake = array_values(array_diff($this->selectedRetake, [$course->id]));
            $this->selectedImprovement = array_values(array_diff($this->selectedImprovement, [$course->id]));
            $this->selectedDue = array_values(array_diff($this->selectedDue, [$course->id]));
        }
    }

    protected function findCourseInBuckets(int $courseId): ?Course
    {
        return $this->retakeCourses->firstWhere('id', $courseId)
            ?? $this->improvementCourses->firstWhere('id', $courseId)
            ?? $this->dueCourses->firstWhere('id', $courseId);
    }

    /**
     * Fee tickets blocking this student from registering, if any.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\StudentFeeTicket>
     */
    public function getOutstandingTicketsProperty(): \Illuminate\Database\Eloquent\Collection
    {
        if (! $this->student) {
            return new \Illuminate\Database\Eloquent\Collection;
        }

        return app(RegistrationBillingService::class)->outstandingTickets($this->student);
    }

    public function getHasOutstandingFeesProperty(): bool
    {
        return $this->outstandingTickets->isNotEmpty();
    }

    /**
     * What the current selection will cost, so the cost is visible before committing.
     *
     * @return array<string, mixed>|null
     */
    public function getCostQuoteProperty(): ?array
    {
        if (! $this->student || ! $this->currentYear || ! $this->currentSemester) {
            return null;
        }

        $registration = Registration::query()
            ->where('student_id', $this->student->id)
            ->where('year_id', $this->currentYear->id)
            ->where('semester', $this->currentSemester->value)
            ->first();

        $courseIds = array_values(array_unique(array_merge(
            $this->selectedRetake,
            $this->selectedImprovement,
            $this->selectedDue
        )));

        return app(RegistrationBillingService::class)->quote($this->student, $registration, $courseIds);
    }

    public function render()
    {
        if ($this->student && $this->registrationAvailable) {
            $this->loadBuckets();
        }

        return view('livewire.student.course-registration.index')
            ->extends('student.layouts.app')
            ->section('content');
    }
}
