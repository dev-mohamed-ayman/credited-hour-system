<?php

namespace App\Livewire\Admin\CourseRegistration;

use App\Enums\Semester;
use App\Models\Department;
use App\Models\Level;
use App\Models\Section;
use App\Models\Year;
use App\Services\BulkCourseRegistrationService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class BulkRegister extends Component
{
    public ?int $departmentId = null;

    public ?int $sectionId = null;

    /** @var array{registered: array<int, array{code: string, name: string, courses: int}>, skipped: array<int, array{code: string, name: string, reason: string}>}|null */
    public ?array $result = null;

    public function updatedDepartmentId(): void
    {
        $this->sectionId = null;
    }

    public function register(BulkCourseRegistrationService $service): void
    {
        abort_unless(auth()->user()->can('course_registrations.create'), 403);

        $this->validate([
            'departmentId' => ['nullable', 'integer', 'exists:departments,id'],
            'sectionId' => ['nullable', 'integer', 'exists:sections,id'],
        ]);

        $year = Year::current();
        $semester = Year::currentSemester();

        if (! $year || ! in_array($semester, [Semester::FIRST, Semester::SECOND], true)) {
            $this->addError('register', 'التسجيل التلقائي متاح فقط أثناء الترم الأول أو الثاني الحالي.');

            return;
        }

        $firstLevel = $this->firstLevel();

        if (! $firstLevel) {
            $this->addError('register', 'لا توجد فرقة أولى معرّفة في النظام.');

            return;
        }

        $this->result = $service->register(
            $year,
            $semester,
            $firstLevel->id,
            $this->departmentId ?: null,
            $this->sectionId ?: null,
        );

        $this->dispatch('toast', [
            'type' => 'success',
            'message' => 'تم تسجيل '.count($this->result['registered']).' طالب تلقائياً، وتخطي '.count($this->result['skipped']).' طالب.',
        ]);
    }

    private function firstLevel(): ?Level
    {
        return Level::query()->orderBy('id')->first(['id', 'name']);
    }

    public function render(): View
    {
        return view('livewire.admin.course-registration.bulk-register', [
            'currentYear' => Year::current(),
            'currentSemester' => Year::currentSemester(),
            'firstLevel' => $this->firstLevel(),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'sections' => Section::query()
                ->when($this->departmentId, fn ($query) => $query->where('department_id', $this->departmentId))
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }
}
