<?php

namespace App\Livewire\Admin\Finance;

use App\Models\AdditionalFee;
use App\Models\MilitaryEducationEnrollment;
use App\Models\RegistrationFee;
use App\Models\Student;
use App\Models\StudentFeeTicket;
use App\Models\Year;
use Illuminate\Support\Facades\DB;
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

    public function mount()
    {
        //
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

        $currentYear = Year::current();
        $currentSemester = Year::currentSemester();

        // 1. Fetch Additional Fees applicable to the student for current year/semester, not yet invoiced
        $this->additionalFees = AdditionalFee::where(function ($q) {
            $q->where('gender', 'both')->orWhere('gender', $this->student->gender);
        })
            ->whereHas('departments', fn ($q) => $q->where('departments.id', $this->student->section->department_id))
            ->whereHas('levels', fn ($q) => $q->where('levels.id', $this->student->level_id))
            ->whereHas('sections', fn ($q) => $q->where('sections.id', $this->student->section_id))
            ->when($currentYear, function ($q) use ($currentYear) {
                $q->where('year_id', $currentYear->id);
            })
            ->when($currentSemester, function ($q) use ($currentSemester) {
                $q->where('semester', $currentSemester);
            })
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
            ->filter(function ($fee) use ($currentYear, $currentSemester) {
                // Check if already invoiced for current year/semester
                return ! StudentFeeTicket::where('student_id', $this->student->id)
                    ->where('fee_type', 'registration')
                    ->where('fee_id', $fee->id)
                    ->where('year_id', $currentYear?->id)
                    ->where('semester', $currentSemester)
                    ->whereIn('status', ['pending', 'paid'])
                    ->exists();
            });

        // 3. Fetch Military Education Course Enrollments in active courses that don't have a ticket yet
        $this->militaryEducationFees = MilitaryEducationEnrollment::where('student_id', $this->student->id)
            ->whereHas('course', fn ($q) => $q->where('status', 'active'))
            ->with('course')
            ->get()
            ->filter(function ($enrollment) {
                return ! StudentFeeTicket::where('student_id', $this->student->id)
                    ->where('fee_type', 'military_education')
                    ->where('fee_id', $enrollment->course_id)
                    ->whereIn('status', ['pending', 'paid'])
                    ->exists();
            });

        $this->selectedFees = [];
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
            $currentYear = Year::current();
            $currentSemester = Year::currentSemester();
            foreach ($this->selectedFees as $feeKey) {
                [$type, $id] = explode('-', $feeKey);

                $amount = 0;
                $feeName = '';
                $departmentId = null;
                $levelId = null;
                $sectionId = null;
                $gender = null;
                $feeDetails = [];
                $yearId = $currentYear?->id;
                $semester = $currentSemester;

                if ($type === 'additional') {
                    $fee = AdditionalFee::with('items', 'departments', 'levels', 'sections', 'year')->find($id);
                    $amount = $fee->amount;
                    $feeName = $fee->name;
                    $departmentId = $this->student->section->department_id;
                    $levelId = $this->student->level_id;
                    $sectionId = $this->student->section_id;
                    $gender = $fee->gender;
                    $feeDetails = [
                        'name' => $fee->name,
                        'gender' => $fee->gender,
                        'amount' => $fee->amount,
                        'is_one_time' => $fee->is_one_time,
                        'year_id' => $fee->year_id,
                        'year_name' => $fee->year?->year,
                        'semester' => $fee->semester?->value,
                        'semester_label' => $fee->semester?->label(),
                        'items' => $fee->items->map(fn ($item) => [
                            'name' => $item->name,
                            'amount' => $item->amount,
                        ])->toArray(),
                        'departments' => $fee->departments->map(fn ($d) => ['id' => $d->id, 'name' => $d->name])->toArray(),
                        'levels' => $fee->levels->map(fn ($l) => ['id' => $l->id, 'name' => $l->name])->toArray(),
                        'sections' => $fee->sections->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->toArray(),
                    ];
                } elseif ($type === 'military_education') {
                    $enrollment = MilitaryEducationEnrollment::with('course', 'year')->find($id);
                    $course = $enrollment->course;
                    $amount = $course->fee_amount;
                    $feeName = 'مصاريف تربيه عسكريه - '.$course->name;
                    $departmentId = $this->student->section->department_id;
                    $levelId = $this->student->level_id;
                    $sectionId = $this->student->section_id;
                    $gender = $course->gender;
                    $yearId = $enrollment->year_id;
                    $semester = $enrollment->semester;
                    $feeDetails = [
                        'course_id' => $course->id,
                        'course_name' => $course->name,
                        'enrollment_id' => $enrollment->id,
                        'amount' => $course->fee_amount,
                    ];
                } else {
                    $fee = RegistrationFee::with('department', 'level')->find($id);
                    $amount = $fee->total_student_payment;
                    $feeName = 'مصاريف تسجيل - '.$fee->department->name.' - '.$fee->level->name;
                    $departmentId = $fee->department_id;
                    $levelId = $fee->level_id;
                    $sectionId = $this->student->section_id;
                    $feeDetails = [
                        'department_id' => $fee->department_id,
                        'department_name' => $fee->department->name,
                        'level_id' => $fee->level_id,
                        'level_name' => $fee->level->name,
                        'hour_payment' => $fee->hour_payment,
                        'ministerial_payment' => $fee->ministerial_payment,
                        'hour_payment_remaining' => $fee->hour_payment_remaining,
                        'ministerial_payment_remaining' => $fee->ministerial_payment_remaining,
                        'total_student_payment' => $fee->total_student_payment,
                        'student_registration_hour' => $fee->student_registration_hour,
                        'number_of_students_per_section' => $fee->number_of_students_per_section,
                    ];
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
                    'fee_name' => $feeName,
                    'amount' => $amount,
                    'status' => 'pending',
                    'notes' => $this->notes,
                    'year_id' => $yearId,
                    'semester' => $semester,
                    'department_id' => $departmentId,
                    'level_id' => $levelId,
                    'section_id' => $sectionId,
                    'gender' => $gender,
                    'fee_details' => $feeDetails,
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
