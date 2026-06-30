<?php

namespace App\Livewire\Admin\CourseRegistration;

use App\Enums\Semester;
use App\Models\Course;
use App\Models\Registration;
use App\Models\Student;
use App\Models\Year;
use App\Services\CourseEligibilityService;
use App\Services\CourseRegistrationService;
use Illuminate\Support\Collection;
use Livewire\Component;

class Index extends Component
{
    public string $searchCode = '';

    public ?Student $student = null;

    public bool $searched = false;

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

        if ($this->currentSemester === Semester::SUMMER) {
            $this->registrationAvailable = false;
        }
    }

    public function search(): void
    {
        $this->validate([
            'searchCode' => 'required|string',
        ]);

        $this->student = Student::query()
            ->where('username', trim($this->searchCode))
            ->with(['level', 'section.department', 'year'])
            ->first();

        $this->searched = true;
        $this->resetSelections();

        if (! $this->student) {
            $this->dispatch('toast', ['type' => 'error', 'message' => 'لم يتم العثور على طالب بهذا الكود.']);

            return;
        }

        if (! $this->registrationAvailable || ! $this->currentYear || ! $this->currentSemester) {
            return;
        }

        if (! in_array($this->currentSemester, [Semester::FIRST, Semester::SECOND], true)) {
            $this->registrationAvailable = false;

            return;
        }

        $this->loadBuckets();
    }

    public function clear(): void
    {
        $this->searchCode = '';
        $this->student = null;
        $this->searched = false;
        $this->resetSelections();
        $this->retakeCourses = collect();
        $this->improvementCourses = collect();
        $this->dueCourses = collect();
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
        abort_unless(auth()->user()->can('course_registrations.create'), 403);

        if (! $this->student || ! $this->currentYear || ! $this->currentSemester) {
            $this->dispatch('toast', ['type' => 'error', 'message' => 'يرجى اختيار طالب أولاً.']);

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

    public function render()
    {
        abort_unless(auth()->user()->can('course_registrations.view'), 403);

        return view('livewire.admin.course-registration.index')
            ->extends('admin.layouts.app')
            ->section('content');
    }
}
