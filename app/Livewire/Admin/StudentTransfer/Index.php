<?php

namespace App\Livewire\Admin\StudentTransfer;

use App\Enums\TransferRequestStatus;
use App\Exceptions\TransferRequestException;
use App\Models\Department;
use App\Models\Level;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentTransferRequest;
use App\Services\StudentTransferService;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $searchStudent = '';

    public ?string $searchStatus = null;

    public ?int $searchDepartment = null;

    /** Student looked up for the new-request form. */
    public ?Student $student = null;

    public string $studentCode = '';

    public ?int $toDepartmentId = null;

    public ?int $toSectionId = null;

    public ?int $toLevelId = null;

    public string $reason = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('student_transfers.view'), 403);
    }

    public function updatingSearchStudent(): void
    {
        $this->resetPage();
    }

    public function updatingSearchStatus(): void
    {
        $this->resetPage();
    }

    public function updatingSearchDepartment(): void
    {
        $this->resetPage();
    }

    public function updatedToDepartmentId(): void
    {
        $this->toSectionId = null;
    }

    public function clearFilters(): void
    {
        $this->reset(['searchStudent', 'searchStatus', 'searchDepartment']);
        $this->resetPage();
    }

    public function searchStudent(): void
    {
        $this->validate(
            ['studentCode' => 'required|string'],
            ['studentCode.required' => 'يجب إدخال كود الطالب'],
        );

        $student = Student::query()
            ->where('username', $this->studentCode)
            ->with(['level', 'section.department'])
            ->first();

        if (! $student) {
            $this->student = null;
            $this->dispatch('alert', ['type' => 'error', 'message' => 'لم يتم العثور على طالب بهذا الكود']);

            return;
        }

        $this->student = $student;
        $this->toDepartmentId = null;
        $this->toSectionId = null;
        $this->toLevelId = $student->level_id;
    }

    public function createRequest(StudentTransferService $transferService): void
    {
        abort_unless(auth()->user()->can('student_transfers.create'), 403);

        if (! $this->student) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'يجب البحث عن الطالب أولاً']);

            return;
        }

        $this->validate([
            'toDepartmentId' => 'required|exists:departments,id',
            'toSectionId' => 'required|exists:sections,id',
            'toLevelId' => 'required|exists:levels,id',
            'reason' => 'nullable|string|max:1000',
        ], [
            'toDepartmentId.required' => 'يجب اختيار التخصص الجديد',
            'toSectionId.required' => 'يجب اختيار الشعبة الجديدة',
            'toLevelId.required' => 'يجب اختيار الفرقة',
        ]);

        try {
            $request = $transferService->create(
                student: $this->student,
                toSectionId: (int) $this->toSectionId,
                toLevelId: (int) $this->toLevelId,
                reason: $this->reason ?: null,
                actor: auth()->user(),
            );
        } catch (TransferRequestException $exception) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $exception->getMessage()]);

            return;
        }

        $this->reset(['student', 'studentCode', 'toDepartmentId', 'toSectionId', 'toLevelId', 'reason']);

        $this->redirectRoute('student-transfers.show', $request->id, navigate: false);
    }

    public function render(): View
    {
        $requests = StudentTransferRequest::query()
            ->with(['student', 'fromDepartment', 'toDepartment', 'toSection', 'toLevel'])
            ->when($this->searchStudent !== '', fn ($query) => $query->whereHas(
                'student',
                fn ($studentQuery) => $studentQuery
                    ->where('name', 'like', "%{$this->searchStudent}%")
                    ->orWhere('username', 'like', "%{$this->searchStudent}%")
            ))
            ->when($this->searchStatus, fn ($query) => $query->where('status', $this->searchStatus))
            ->when($this->searchDepartment, fn ($query) => $query->where(fn ($scoped) => $scoped
                ->where('from_department_id', $this->searchDepartment)
                ->orWhere('to_department_id', $this->searchDepartment)))
            ->latest('id')
            ->paginate(15);

        return view('livewire.admin.student-transfer.index', [
            'requests' => $requests,
            'departments' => Department::query()->orderBy('name')->get(),
            'levels' => Level::query()->orderBy('id')->get(),
            'targetSections' => $this->toDepartmentId
                ? Section::query()->where('department_id', $this->toDepartmentId)->orderBy('name')->get()
                : collect(),
            'statuses' => TransferRequestStatus::cases(),
        ])->extends('admin.layouts.app')->section('content');
    }
}
