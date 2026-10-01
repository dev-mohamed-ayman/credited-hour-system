<?php

namespace App\Livewire\Admin\RegistrationLog;

use App\Enums\RegistrationStatus;
use App\Enums\Semester;
use App\Models\Course;
use App\Models\Department;
use App\Models\RegistrationCourse;
use App\Models\Year;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public ?int $yearId = null;

    public ?string $semester = null;

    public ?int $departmentId = null;

    public ?int $courseId = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->can('course_registrations.view'), 403);

        $this->yearId = Year::current()?->id;
        $this->semester = Year::currentSemester()?->value;
    }

    public function updated(string $property): void
    {
        if ($property === 'departmentId') {
            $this->courseId = null;
        }

        $this->resetPage();
    }

    /**
     * Students holding the selected course in the selected term. Rejected and
     * cancelled registrations do not count as enrolled.
     */
    private function enrollmentsQuery(): Builder
    {
        return RegistrationCourse::query()
            ->where('course_id', $this->courseId)
            ->whereHas('registration', fn (Builder $registration) => $registration
                ->where('year_id', $this->yearId)
                ->where('semester', $this->semester)
                ->whereIn('status', [RegistrationStatus::PENDING->value, RegistrationStatus::APPROVED->value]));
    }

    public function render(): View
    {
        $isReady = $this->yearId && $this->semester && $this->courseId;

        $enrollments = $isReady
            ? $this->enrollmentsQuery()
                ->with(['registration.student.level', 'registration.student.section.department', 'grade'])
                ->join('registrations', 'registrations.id', '=', 'registration_courses.registration_id')
                ->join('students', 'students.id', '=', 'registrations.student_id')
                ->orderBy('students.username')
                ->select('registration_courses.*')
                ->paginate(50)
            : null;

        return view('livewire.admin.registration-log.index', [
            'years' => Year::query()->latest()->get(['id', 'year']),
            'semesters' => Semester::cases(),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'courses' => Course::query()
                ->when($this->departmentId, fn (Builder $query) => $query->where('department_id', $this->departmentId))
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
            'enrollments' => $enrollments,
            'selectedCourse' => $this->courseId ? Course::find($this->courseId) : null,
        ])
            ->extends('admin.layouts.app')
            ->section('content');
    }
}
