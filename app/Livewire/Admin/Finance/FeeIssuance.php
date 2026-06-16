<?php

namespace App\Livewire\Admin\Finance;

use App\Models\AdditionalFee;
use App\Models\RegistrationFee;
use App\Models\Student;
use App\Models\StudentFeeTicket;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class FeeIssuance extends Component
{
    public $studentCode;

    public $student;

    public $additionalFees = [];

    public $registrationFees = [];

    public $selectedFees = []; // Array of 'type-id'

    public $pendingTickets = [];

    public $notes;

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
            ->get();
    }

    public function deleteTicket($ticketId)
    {
        $ticket = StudentFeeTicket::find($ticketId);
        if ($ticket && $ticket->status === 'pending') {
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

        // 1. Fetch Additional Fees applicable to the student
        // These are fees targeting the student's department, level, section, and gender
        $this->additionalFees = AdditionalFee::where(function ($q) {
            $q->where('gender', 'both')->orWhere('gender', $this->student->gender);
        })
            ->whereHas('departments', fn ($q) => $q->where('departments.id', $this->student->section->department_id))
            ->whereHas('levels', fn ($q) => $q->where('levels.id', $this->student->level_id))
            ->whereHas('sections', fn ($q) => $q->where('sections.id', $this->student->section_id))
            ->get()
            ->filter(function ($fee) {
                // Exclude if already invoiced (pending or paid)
                return ! StudentFeeTicket::where('student_id', $this->student->id)
                    ->where('fee_type', 'additional')
                    ->where('fee_id', $fee->id)
                    ->whereIn('status', ['pending', 'paid'])
                    ->exists();
            });

        // 2. Fetch Registration Fees
        $this->registrationFees = RegistrationFee::where('department_id', $this->student->section->department_id)
            ->where('level_id', $this->student->level_id)
            ->get()
            ->filter(function ($fee) {
                // Exclude if already invoiced (pending or paid)
                return ! StudentFeeTicket::where('student_id', $this->student->id)
                    ->where('fee_type', 'registration')
                    ->where('fee_id', $fee->id)
                    ->whereIn('status', ['pending', 'paid'])
                    ->exists();
            });

        $this->selectedFees = [];
    }

    public function generateTickets()
    {
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
            foreach ($this->selectedFees as $feeKey) {
                [$type, $id] = explode('-', $feeKey);

                $amount = 0;
                if ($type === 'additional') {
                    $fee = AdditionalFee::find($id);
                    $amount = $fee->amount;
                } else {
                    $fee = RegistrationFee::find($id);
                    $amount = $fee->total_student_payment; // Or whatever column stores the total
                }

                // Generate unique ticket number: YearLastTwoDigitsMonthDayHourMinuteSecondStudentCode
                $ticketNumber = date('ymdHis').$this->student->username;

                // Ensure uniqueness (unlikely but safe)
                while (StudentFeeTicket::where('ticket_number', $ticketNumber)->exists()) {
                    sleep(1);
                    $ticketNumber = date('ymdHis').$this->student->username;
                }

                StudentFeeTicket::create([
                    'ticket_number' => $ticketNumber,
                    'student_id' => $this->student->id,
                    'fee_type' => $type,
                    'fee_id' => $id,
                    'amount' => $amount,
                    'status' => 'pending',
                    'notes' => $this->notes,
                ]);

                $ticketNumbers[] = $ticketNumber;
            }
        });

        // Redirect to print page with ticket numbers
        return redirect()->route('admin.finance.print-tickets', [
            'tickets' => implode(',', $ticketNumbers),
        ]);
    }

    public function render()
    {
        return view('livewire.admin.finance.fee-issuance')
            ->extends('admin.layouts.app')
            ->section('content');
    }
}
