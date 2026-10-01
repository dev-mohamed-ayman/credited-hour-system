<?php

namespace App\Services;

use App\Enums\DiscountScope;
use App\Enums\DiscountStatus;
use App\Models\AdditionalFee;
use App\Models\MilitaryEducationEnrollment;
use App\Models\RegistrationFee;
use App\Models\Student;
use App\Models\StudentDiscount;
use App\Models\StudentFeeTicket;
use App\Models\User;
use App\Models\Year;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FeeTicketService
{
    public function __construct(public DiscountService $discounts) {}

    /**
     * Additional fees of the current term that apply to the student and are not invoiced yet.
     *
     * @return Collection<int, AdditionalFee>
     */
    public function availableAdditionalFees(Student $student): Collection
    {
        $currentYear = Year::current();
        $currentSemester = Year::currentSemester();

        return AdditionalFee::where(function ($q) use ($student) {
            $q->where('gender', 'both')->orWhere('gender', $student->gender);
        })
            ->whereHas('departments', fn ($q) => $q->where('departments.id', $student->section->department_id))
            ->whereHas('levels', fn ($q) => $q->where('levels.id', $student->level_id))
            ->whereHas('sections', fn ($q) => $q->where('sections.id', $student->section_id))
            ->when($currentYear, function ($q) use ($currentYear) {
                $q->where('year_id', $currentYear->id);
            })
            ->when($currentSemester, function ($q) use ($currentSemester) {
                $q->where('semester', $currentSemester);
            })
            ->get()
            ->filter(function ($fee) use ($student) {
                return ! StudentFeeTicket::where('student_id', $student->id)
                    ->where('fee_type', 'additional')
                    ->where('fee_id', $fee->id)
                    ->whereIn('status', ['pending', 'paid'])
                    ->exists();
            });
    }

    /**
     * Registration fees of the student's department/level not invoiced for the current term.
     *
     * @return Collection<int, RegistrationFee>
     */
    public function availableRegistrationFees(Student $student): Collection
    {
        $currentYear = Year::current();
        $currentSemester = Year::currentSemester();

        return RegistrationFee::where('department_id', $student->section->department_id)
            ->where('level_id', $student->level_id)
            ->get()
            ->filter(function ($fee) use ($student, $currentYear, $currentSemester) {
                return ! StudentFeeTicket::where('student_id', $student->id)
                    ->where('fee_type', 'registration')
                    ->where('fee_id', $fee->id)
                    ->where('year_id', $currentYear?->id)
                    ->where('semester', $currentSemester)
                    ->whereIn('status', ['pending', 'paid'])
                    ->exists();
            });
    }

    /**
     * Enrollments in active military education courses that have no ticket yet.
     *
     * @return Collection<int, MilitaryEducationEnrollment>
     */
    public function availableMilitaryEducationEnrollments(Student $student): Collection
    {
        return MilitaryEducationEnrollment::where('student_id', $student->id)
            ->whereHas('course', fn ($q) => $q->where('status', 'active'))
            ->with('course')
            ->get()
            ->filter(function ($enrollment) use ($student) {
                return ! StudentFeeTicket::where('student_id', $student->id)
                    ->where('fee_type', 'military_education')
                    ->where('fee_id', $enrollment->course_id)
                    ->whereIn('status', ['pending', 'paid'])
                    ->exists();
            });
    }

    /**
     * Issue one ticket for a fee line, apply every eligible discount, and settle
     * it on the spot when the discounts cover the whole amount.
     *
     * @param  array{name: string, amount: float|int|string}|null  $otherFee  required when $type is 'other'
     */
    public function issue(Student $student, string $type, string $id, ?string $notes = null, ?User $actor = null, ?array $otherFee = null): StudentFeeTicket
    {
        $line = $this->buildLine($student, $type, $id, $otherFee);

        $ticket = StudentFeeTicket::create([
            'ticket_number' => $this->nextTicketNumber($student),
            'student_id' => $student->id,
            'fee_type' => $type,
            'fee_id' => $line['fee_id'],
            'fee_name' => $line['fee_name'],
            'amount' => $line['amount'],
            'status' => 'pending',
            'notes' => $notes,
            'year_id' => $line['year_id'],
            'semester' => $line['semester'],
            'department_id' => $line['department_id'],
            'level_id' => $line['level_id'],
            'section_id' => $line['section_id'],
            'gender' => $line['gender'],
            'fee_details' => $line['fee_details'],
        ]);

        $plan = $this->discounts->planApplication(
            $this->discounts->eligibleFor($student, $type, $line['fee_id'] ?: null, $line['year_id'], $line['semester']),
            (string) $line['amount']
        );
        $this->discounts->logApplicationNotices($plan['notices']);
        $this->discounts->applyToTicket($ticket, $plan['applied'], $actor);
        $this->discounts->settleIfFullyDiscounted($ticket->refresh());

        return $ticket->refresh();
    }

    /**
     * After granting a discount on a specific fee (registration, or one additional
     * fee), issue-and-settle every uninvoiced line it fully covers, and settle any
     * pending ticket it brings down to zero — no separate payment step needed.
     * Any-scope discounts target no specific fee and are left to normal issuance.
     *
     * @return Collection<int, StudentFeeTicket> the tickets settled by this call
     */
    public function settleFullyDiscountedFees(StudentDiscount $discount, User $actor): Collection
    {
        if ($discount->scope === DiscountScope::Any
            || in_array($discount->status, [DiscountStatus::Revoked, DiscountStatus::Exhausted], true)) {
            return collect();
        }

        return DB::transaction(function () use ($discount, $actor) {
            $student = $discount->student()->with('section')->firstOrFail();
            $settled = collect();

            foreach ($this->fullyCoveredPendingTickets($discount, $student) as $ticket) {
                $this->discounts->applyToPendingTicket($discount, $ticket, $actor);

                if ($ticket->refresh()->isPaid()) {
                    $settled->push($ticket);
                }
            }

            foreach ($this->fullyCoveredNewLines($discount, $student) as [$type, $id]) {
                $ticket = $this->issue($student, $type, (string) $id, null, $actor);

                if ($ticket->isPaid()) {
                    $settled->push($ticket);
                }
            }

            return $settled;
        });
    }

    /**
     * @return Collection<int, StudentFeeTicket>
     */
    private function fullyCoveredPendingTickets(StudentDiscount $discount, Student $student): Collection
    {
        return StudentFeeTicket::where('student_id', $student->id)
            ->where('status', 'pending')
            ->get()
            ->filter(function (StudentFeeTicket $ticket) use ($discount, $student) {
                $feeId = $ticket->fee_id ? (int) $ticket->fee_id : null;

                if (! $discount->appliesTo($ticket->fee_type, $feeId, $ticket->year_id, $ticket->semester)) {
                    return false;
                }

                if ($ticket->discountUsages()->where('student_discount_id', $discount->id)->exists()) {
                    return false;
                }

                return $this->isFullyCovered($student, $ticket->fee_type, $feeId, $ticket->year_id, $ticket->semester, (string) $ticket->grossAmount());
            })
            ->values();
    }

    /**
     * @return array<int, array{0: string, 1: int}> [fee type, fee id] pairs
     */
    private function fullyCoveredNewLines(StudentDiscount $discount, Student $student): array
    {
        $yearId = Year::current()?->id;
        $semester = Year::currentSemester();
        $additionalFees = $this->availableAdditionalFees($student);
        $lines = [];

        if ($discount->scope === DiscountScope::Additional) {
            foreach ($additionalFees->where('id', $discount->fee_id) as $fee) {
                if ($this->isFullyCovered($student, 'additional', $fee->id, $yearId, $semester, (string) $fee->amount)) {
                    $lines[] = ['additional', $fee->id];
                }
            }
        }

        if ($discount->scope === DiscountScope::Registration && $additionalFees->isEmpty()) {
            foreach ($this->availableRegistrationFees($student) as $fee) {
                if ($this->isFullyCovered($student, 'registration', $fee->id, $yearId, $semester, (string) $fee->total_student_payment)) {
                    $lines[] = ['registration', $fee->id];
                }
            }
        }

        return $lines;
    }

    private function isFullyCovered(Student $student, string $feeType, ?int $feeId, ?int $yearId, mixed $semester, string $amount): bool
    {
        if ((float) $amount <= 0) {
            return false;
        }

        $plan = $this->discounts->planApplication(
            $this->discounts->eligibleFor($student, $feeType, $feeId, $yearId, $semester),
            $amount
        );

        return ! empty($plan['applied']) && (float) $plan['net'] <= 0;
    }

    /**
     * Snapshot of everything a ticket stores about its fee line.
     *
     * @param  array{name: string, amount: float|int|string}|null  $otherFee
     * @return array{fee_id: int, fee_name: string, amount: mixed, year_id: ?int, semester: mixed, department_id: ?int, level_id: ?int, section_id: ?int, gender: ?string, fee_details: array<string, mixed>}
     */
    private function buildLine(Student $student, string $type, string $id, ?array $otherFee): array
    {
        $line = [
            'fee_id' => (int) $id,
            'year_id' => Year::current()?->id,
            'semester' => Year::currentSemester(),
            'department_id' => $student->section->department_id,
            'level_id' => $student->level_id,
            'section_id' => $student->section_id,
            'gender' => null,
        ];

        if ($type === 'additional') {
            $fee = AdditionalFee::with('items', 'departments', 'levels', 'sections', 'year')->findOrFail($id);

            return [
                ...$line,
                'amount' => $fee->amount,
                'fee_name' => $fee->name,
                'gender' => $fee->gender,
                'fee_details' => [
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
                ],
            ];
        }

        if ($type === 'military_education') {
            $enrollment = MilitaryEducationEnrollment::with('course', 'year')->findOrFail($id);
            $course = $enrollment->course;

            return [
                ...$line,
                'amount' => $course->fee_amount,
                'fee_name' => 'مصاريف تربيه عسكريه - '.$course->name,
                'gender' => $course->gender,
                'year_id' => $enrollment->year_id,
                'semester' => $enrollment->semester,
                'fee_details' => [
                    'course_id' => $course->id,
                    'course_name' => $course->name,
                    'enrollment_id' => $enrollment->id,
                    'amount' => $course->fee_amount,
                ],
            ];
        }

        if ($type === 'other') {
            return [
                ...$line,
                'fee_id' => 0,
                'amount' => $otherFee['amount'],
                'fee_name' => 'مصاريف أخرى - '.$otherFee['name'],
                'fee_details' => [
                    'name' => $otherFee['name'],
                    'amount' => $otherFee['amount'],
                ],
            ];
        }

        $fee = RegistrationFee::with('department', 'level')->findOrFail($id);

        return [
            ...$line,
            'amount' => $fee->total_student_payment,
            'fee_name' => 'مصاريف تسجيل - '.$fee->department->name.' - '.$fee->level->name,
            'department_id' => $fee->department_id,
            'level_id' => $fee->level_id,
            'fee_details' => [
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
            ],
        ];
    }

    /**
     * Ticket number format: YearLastTwoDigitsMonthDayHourMinuteSecondStudentCode.
     */
    private function nextTicketNumber(Student $student): string
    {
        $ticketNumber = date('ymdHis').$student->username;

        while (StudentFeeTicket::where('ticket_number', $ticketNumber)->exists()) {
            sleep(1);
            $ticketNumber = date('ymdHis').$student->username;
        }

        return $ticketNumber;
    }
}
