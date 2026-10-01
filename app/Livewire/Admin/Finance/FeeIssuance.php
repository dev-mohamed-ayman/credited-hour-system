<?php

namespace App\Livewire\Admin\Finance;

use App\Models\FeeTemplate;
use App\Models\Student;
use App\Models\StudentFeeTicket;
use App\Models\Year;
use App\Services\DiscountService;
use App\Services\FeeTicketService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;

class FeeIssuance extends Component
{
    public $studentCode;

    public $student;

    public $additionalFees = [];

    public $registrationFees = [];

    public $militaryEducationFees = [];

    public $selectedFees = []; // Array of 'type-id'

    public $pendingTickets = [];

    public $notes;

    /** @var array<int, array{id: string, name: string, amount: float}> */
    public array $otherFees = [];

    public $selectedFeeTemplateId = '';

    public $feeTemplates = [];

    /** @var array<string, array{original: string, discount: string, net: string}> */
    public array $discountSummaries = [];

    public function mount()
    {
        $this->loadFeeTemplates();
    }

    public function loadFeeTemplates(): void
    {
        try {
            $this->feeTemplates = FeeTemplate::active()
                ->orderBy('name')
                ->get(['id', 'name', 'amount'])
                ->toArray();
        } catch (\Throwable) {
            $this->feeTemplates = [];
        }
    }

    public function updatedSelectedFeeTemplateId(): void
    {
        if (empty($this->selectedFeeTemplateId)) {
            return;
        }

        try {
            $template = FeeTemplate::find((int) $this->selectedFeeTemplateId);
        } catch (\Throwable) {
            $template = null;
        }

        if ($template) {
            abort_unless(auth()->user()->can('finance.create'), 403);

            $feeId = (string) Str::uuid();

            $this->otherFees[] = [
                'id' => $feeId,
                'name' => $template->name,
                'amount' => (float) $template->amount,
            ];

            $this->selectedFees[] = 'other-'.$feeId;

            $this->dispatch('alert', ['type' => 'success', 'message' => 'تمت إضافة «'.$template->name.'» بنجاح']);
        }

        $this->selectedFeeTemplateId = '';
    }

    public function searchStudent()
    {
        $this->validate([
            'studentCode' => 'required',
        ]);

        $this->student = Student::where('username', $this->studentCode)->first();

        if (! $this->student) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'لم يتم العثور على طالب بهذا الكود']);

            return;
        }

        $this->otherFees = [];
        $this->selectedFeeTemplateId = '';
        $this->loadFeeTemplates();

        $this->loadFees();
        $this->loadPendingTickets();
    }

    public function loadPendingTickets()
    {
        if (! $this->student) {
            $this->pendingTickets = [];

            return;
        }

        $this->pendingTickets = StudentFeeTicket::where('student_id', $this->student->id)
            ->where('status', 'pending')
            ->with('year')
            ->get();
    }

    public function deleteTicket($ticketId)
    {
        abort_unless(auth()->user()->can('finance.delete'), 403);

        $ticket = StudentFeeTicket::find($ticketId);
        if ($ticket && $ticket->status === 'pending') {
            app(DiscountService::class)->revertTicket($ticket, auth()->user());
            $ticket->delete();
            $this->dispatch('alert', ['type' => 'success', 'message' => 'تم حذف الحافظة بنجاح']);
            $this->loadFees();
            $this->loadPendingTickets();
        }
    }

    public function printTicket($ticketNumber)
    {
        return redirect()->route('admin.finance.print-tickets', [
            'tickets' => $ticketNumber,
        ]);
    }

    public function loadFees()
    {
        if (! $this->student) {
            return;
        }

        $fees = app(FeeTicketService::class);

        $this->additionalFees = $fees->availableAdditionalFees($this->student);
        $this->registrationFees = $fees->availableRegistrationFees($this->student);
        $this->militaryEducationFees = $fees->availableMilitaryEducationEnrollments($this->student);

        $this->selectedFees = [];
    }

    public function removeOtherFee(string $feeId): void
    {
        abort_unless(auth()->user()->can('finance.create'), 403);

        $this->otherFees = array_values(array_filter(
            $this->otherFees,
            fn (array $fee) => $fee['id'] !== $feeId
        ));

        $this->selectedFees = array_values(array_filter(
            $this->selectedFees,
            fn (string $key) => $key !== 'other-'.$feeId
        ));
    }

    public function generateTickets()
    {
        abort_unless(auth()->user()->can('finance.create'), 403);

        if (empty($this->selectedFees)) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'برجاء اختيار مصروف واحد على الأقل']);

            return;
        }

        // Validation logic: Additional fees must be paid before Registration fees
        $hasPendingAdditional = $this->additionalFees->pluck('id')->diff(
            collect($this->selectedFees)
                ->filter(fn ($val) => str_starts_with($val, 'additional-'))
                ->map(fn ($val) => (int) str_replace('additional-', '', $val))
        )->isNotEmpty();

        $hasSelectedRegistration = collect($this->selectedFees)
            ->filter(fn ($val) => str_starts_with($val, 'registration-'))
            ->isNotEmpty();

        if ($hasPendingAdditional && $hasSelectedRegistration) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'يجب سداد جميع المصاريف الإضافية أولاً قبل مصاريف التسجيل']);

            return;
        }

        $ticketNumbers = [];

        DB::transaction(function () use (&$ticketNumbers) {
            $fees = app(FeeTicketService::class);
            $issuedOtherFeeIds = [];

            foreach ($this->selectedFees as $feeKey) {
                [$type, $id] = explode('-', $feeKey, 2);
                $otherFee = null;

                if ($type === 'other') {
                    $otherFee = collect($this->otherFees)->firstWhere('id', $id);

                    if (! $otherFee) {
                        continue;
                    }

                    $issuedOtherFeeIds[] = $id;
                }

                $ticket = $fees->issue($this->student, $type, $id, $this->notes, auth()->user(), $otherFee);

                $ticketNumbers[] = $ticket->ticket_number;
            }

            if (! empty($issuedOtherFeeIds)) {
                $this->otherFees = array_values(array_filter(
                    $this->otherFees,
                    fn (array $fee) => ! in_array($fee['id'], $issuedOtherFeeIds, true)
                ));
            }
        });

        // Redirect to print page with ticket numbers
        return redirect()->route('admin.finance.print-tickets', [
            'tickets' => implode(',', $ticketNumbers),
        ]);
    }

    public function render()
    {
        $this->buildDiscountSummaries();

        return view('livewire.admin.finance.fee-issuance')
            ->extends('admin.layouts.app')
            ->section('content');
    }

    /**
     * Expected original/discount/net per selectable fee line, for the pre-issuance
     * preview. Read-only; the real application happens inside generateTickets.
     */
    private function buildDiscountSummaries(): void
    {
        $this->discountSummaries = [];

        if (! $this->student) {
            return;
        }

        $service = app(DiscountService::class);
        $currentYear = Year::current();
        $currentSemester = Year::currentSemester();

        $lines = [];
        foreach ($this->additionalFees as $fee) {
            $lines['additional-'.$fee->id] = ['registration', 'additional', $fee->id, $fee->amount];
        }
        foreach ($this->registrationFees as $fee) {
            $lines['registration-'.$fee->id] = ['registration', 'registration', $fee->id, $fee->total_student_payment];
        }
        foreach ($this->militaryEducationFees as $enrollment) {
            $lines['military_education-'.$enrollment->id] = ['registration', 'military_education', $enrollment->id, $enrollment->course->fee_amount];
        }
        foreach ($this->otherFees as $fee) {
            $lines['other-'.$fee['id']] = ['registration', 'other', null, $fee['amount']];
        }

        foreach ($lines as $key => [$_, $feeType, $feeId, $amount]) {
            $eligible = $service->eligibleFor($this->student, $feeType, $feeId, $currentYear?->id, $currentSemester);
            $plan = $service->planApplication($eligible, (string) $amount);

            if (! empty($plan['applied'])) {
                $this->discountSummaries[$key] = [
                    'original' => number_format((float) $amount, 2),
                    'discount' => number_format((float) $amount - (float) $plan['net'], 2),
                    'net' => number_format((float) $plan['net'], 2),
                ];
            }
        }
    }
}
