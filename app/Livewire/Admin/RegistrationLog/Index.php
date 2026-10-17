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
use Symfony\Component\HttpFoundation\StreamedResponse;

class Index extends Component
{
    use WithPagination;

    public ?int $yearId = null;

    public ?string $semester = null;

    public ?int $departmentId = null;

    public ?int $courseId = null;

    /**
     * Render every enrollment without pagination so the browser prints the full list.
     */
    public bool $printAll = false;

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

        $this->printAll = false;
        $this->resetPage();
    }

    public function printList(): void
    {
        $this->printAll = true;
        $this->resetPage();

        $this->js('setTimeout(() => window.print(), 300)');
    }

    public function exportCsv(): ?StreamedResponse
    {
        abort_unless(auth()->user()->can('course_registrations.view'), 403);

        if (! $this->isReady()) {
            return null;
        }

        $course = Course::find($this->courseId);
        $enrollments = $this->orderedEnrollmentsQuery()->get();

        $filename = 'registration-log-'.($course?->code ?? $this->courseId).'-'.now()->format('Y-m-d-H-i-s').'.csv';

        return response()->streamDownload(function () use ($enrollments) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['#', 'الكود', 'الطالب', 'الفرقة', 'التخصص', 'حالة التسجيل', 'التقدير']);

            foreach ($enrollments as $index => $enrollment) {
                $student = $enrollment->registration->student;

                fputcsv($handle, [
                    $index + 1,
                    $student?->username,
                    $student?->name,
                    $student?->level?->name ?? '—',
                    $student?->section?->department?->name ?? '—',
                    $enrollment->registration->status?->label(),
                    $enrollment->grade?->name ?? '—',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function isReady(): bool
    {
        return $this->yearId && $this->semester && $this->courseId;
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

    private function orderedEnrollmentsQuery(): Builder
    {
        return $this->enrollmentsQuery()
            ->with(['registration.student.level', 'registration.student.section.department', 'grade'])
            ->join('registrations', 'registrations.id', '=', 'registration_courses.registration_id')
            ->join('students', 'students.id', '=', 'registrations.student_id')
            ->orderBy('students.username')
            ->select('registration_courses.*');
    }

    public function render(): View
    {
        $enrollments = null;

        if ($this->isReady()) {
            $query = $this->orderedEnrollmentsQuery();
            $enrollments = $this->printAll
                ? $query->paginate(max($query->count(), 1))
                : $query->paginate(50);
        }

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
