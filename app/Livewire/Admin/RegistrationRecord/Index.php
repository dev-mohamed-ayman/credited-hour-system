<?php

namespace App\Livewire\Admin\RegistrationRecord;

use App\Enums\Semester;
use App\Models\Registration;
use App\Models\Year;
use App\Services\CourseEligibilityService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $searchStudent = '';

    public ?int $searchYear = null;

    public ?Semester $searchSemester = null;

    public string $sortField = 'created_at';

    public string $sortDirection = 'desc';

    public function updatingSearchStudent(): void
    {
        $this->resetPage();
    }

    public function updatingSearchYear(): void
    {
        $this->resetPage();
    }

    public function updatingSearchSemester(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['searchStudent', 'searchYear', 'searchSemester']);
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function deleteRegistration(int $registrationId, CourseEligibilityService $eligibilityService): void
    {
        abort_unless(auth()->user()->can('course_registrations.delete'), 403);

        $registration = Registration::query()->with('courses.course')->find($registrationId);

        if (! $registration) {
            $this->dispatch('toast', message: 'سجل التسجيل غير موجود.', type: 'error');

            return;
        }

        try {
            DB::beginTransaction();

            foreach ($registration->courses as $course) {
                $guard = $eligibilityService->canDeleteRegistrationCourse($course);

                if (! $guard['allowed']) {
                    $blockingCourses = implode(' و ', $guard['blocking_courses']);
                    $this->dispatch('toast',
                        message: "لا يمكن حذف هذا السجل. الطالب مسجل في مادة ({$blockingCourses}) في ترم لاحق بناءً على نجاحه في مادة ({$course->course->name}).",
                        type: 'error'
                    );
                    DB::rollBack();

                    return;
                }
            }

            $registration->courses()->delete();
            $registration->delete();

            DB::commit();

            $this->dispatch('toast', message: 'تم حذف سجل التسجيل بنجاح.', type: 'success');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->dispatch('toast', message: 'حدث خطأ أثناء الحذف.', type: 'error');
        }
    }

    public function render(): View
    {
        abort_unless(auth()->user()->can('course_registrations.view'), 403);

        $registrations = Registration::query()
            ->with(['student.level', 'student.section.department', 'year', 'courses'])
            ->when($this->searchStudent, function ($query) {
                $query->whereHas('student', function ($q) {
                    $q->where('name', 'like', '%'.$this->searchStudent.'%')
                        ->orWhere('username', 'like', '%'.$this->searchStudent.'%');
                });
            })
            ->when($this->searchYear, fn ($query) => $query->where('year_id', $this->searchYear))
            ->when($this->searchSemester, fn ($query) => $query->where('semester', $this->searchSemester))
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(15);

        $years = Year::query()->latest()->get();

        return view('livewire.admin.registration-record.index', [
            'registrations' => $registrations,
            'years' => $years,
        ])
            ->extends('admin.layouts.app')
            ->section('content');
    }
}
