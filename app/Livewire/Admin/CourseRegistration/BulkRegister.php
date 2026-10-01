<?php

namespace App\Livewire\Admin\CourseRegistration;

use App\Enums\Semester;
use App\Models\Department;
use App\Models\Level;
use App\Models\Year;
use App\Services\BulkCourseRegistrationService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

class BulkRegister extends Component
{
    public ?int $yearId = null;

    public ?string $semester = null;

    public ?int $departmentId = null;

    public ?int $levelId = null;

    /** @var array{registered: array<int, array{code: string, name: string, courses: int}>, skipped: array<int, array{code: string, name: string, reason: string}>}|null */
    public ?array $result = null;

    public function mount(): void
    {
        $this->yearId = Year::current()?->id;
        $currentSemester = Year::currentSemester();
        $this->semester = in_array($currentSemester, [Semester::FIRST, Semester::SECOND], true) ? $currentSemester->value : null;
    }

    public function register(BulkCourseRegistrationService $service): void
    {
        abort_unless(auth()->user()->can('course_registrations.create'), 403);

        $this->validate([
            'yearId' => ['required', 'integer', 'exists:years,id'],
            'semester' => ['required', Rule::in([Semester::FIRST->value, Semester::SECOND->value])],
            'departmentId' => ['nullable', 'integer', 'exists:departments,id'],
            'levelId' => ['nullable', 'integer', 'exists:levels,id'],
        ], [
            'yearId.required' => 'برجاء اختيار السنة الدراسية',
            'semester.required' => 'برجاء اختيار الترم',
            'semester.in' => 'التسجيل التلقائي متاح للترم الأول أو الثاني فقط',
        ]);

        $this->result = $service->register(
            Year::findOrFail($this->yearId),
            Semester::from($this->semester),
            $this->departmentId ?: null,
            $this->levelId ?: null,
        );

        $this->dispatch('toast', [
            'type' => 'success',
            'message' => 'تم تسجيل '.count($this->result['registered']).' طالب تلقائياً، وتخطي '.count($this->result['skipped']).' طالب.',
        ]);
    }

    public function render(): View
    {
        return view('livewire.admin.course-registration.bulk-register', [
            'years' => Year::orderByDesc('year')->get(['id', 'year']),
            'semesters' => [Semester::FIRST, Semester::SECOND],
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'levels' => Level::orderBy('id')->get(['id', 'name']),
        ]);
    }
}
