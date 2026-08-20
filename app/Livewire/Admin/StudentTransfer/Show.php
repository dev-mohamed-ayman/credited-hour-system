<?php

namespace App\Livewire\Admin\StudentTransfer;

use App\Exceptions\TransferRequestException;
use App\Models\StudentTransferRequest;
use App\Services\StudentTransferService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Show extends Component
{
    public StudentTransferRequest $transferRequest;

    public string $rejectionReason = '';

    public function mount(StudentTransferRequest $transferRequest): void
    {
        abort_unless(auth()->user()->can('student_transfers.view'), 403);

        $this->transferRequest = $transferRequest->load([
            'student.section.department',
            'student.level',
            'fromDepartment', 'fromSection', 'fromLevel',
            'toDepartment', 'toSection', 'toLevel',
            'year', 'createdByUser', 'decidedByUser',
        ]);
    }

    public function approve(StudentTransferService $transferService): void
    {
        abort_unless(auth()->user()->can('student_transfers.approve'), 403);

        try {
            $result = $transferService->approve($this->transferRequest, auth()->user());
        } catch (TransferRequestException $exception) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $exception->getMessage()]);

            return;
        }

        $this->transferRequest->refresh();

        $amount = number_format($result['refunded'], 2);

        $this->dispatch('alert', [
            'type' => 'success',
            'message' => "تمت الموافقة على التحويل. تم استرداد {$amount} ج.م إلى محفظة الطالب.",
        ]);
    }

    public function reject(StudentTransferService $transferService): void
    {
        abort_unless(auth()->user()->can('student_transfers.reject'), 403);

        $this->validate(
            ['rejectionReason' => 'required|string|max:1000'],
            ['rejectionReason.required' => 'يجب كتابة سبب الرفض'],
        );

        try {
            $transferService->reject($this->transferRequest, auth()->user(), $this->rejectionReason);
        } catch (TransferRequestException $exception) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $exception->getMessage()]);

            return;
        }

        $this->transferRequest->refresh();
        $this->reset('rejectionReason');

        $this->dispatch('alert', ['type' => 'success', 'message' => 'تم رفض طلب التحويل.']);
    }

    public function render(StudentTransferService $transferService): View
    {
        return view('livewire.admin.student-transfer.show', [
            'details' => $transferService->preview($this->transferRequest),
        ])->extends('admin.layouts.app')->section('content');
    }
}
