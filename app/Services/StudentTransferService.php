<?php

namespace App\Services;

use App\Enums\RegistrationStatus;
use App\Enums\Semester;
use App\Enums\TransferRequestStatus;
use App\Exceptions\TransferRequestException;
use App\Models\Registration;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentFeeTicket;
use App\Models\StudentTransferRequest;
use App\Models\User;
use App\Models\Year;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Moves a student from one department to another.
 *
 * A transfer is not a field update: the old department priced the student's term
 * differently, so approving one has to unwind the term first — cancel its course
 * registrations, cancel its fee tickets, and put every pound the student was charged
 * back in their wallet — before the student is moved.
 */
class StudentTransferService
{
    public function __construct(
        protected RegistrationBillingService $billingService,
        protected WalletService $walletService,
    ) {}

    /**
     * Guard the creation of a request. Throws rather than returning a result because
     * every failure here is a mistake the operator has to fix before anything is stored.
     */
    public function assertCanCreate(Student $student, int $toSectionId, int $toLevelId): void
    {
        if ($student->transferRequests()->pending()->exists()) {
            throw new TransferRequestException('يوجد طلب تحويل قيد المراجعة بالفعل لهذا الطالب.');
        }

        $section = Section::query()->find($toSectionId);

        if (! $section) {
            throw new TransferRequestException('الشعبة المختارة غير موجودة.');
        }

        if ($student->section_id === $toSectionId && $student->level_id === $toLevelId) {
            throw new TransferRequestException('الطالب موجود بالفعل في نفس الشعبة والفرقة.');
        }
    }

    /**
     * Create a pending request, freezing where the student stands right now.
     */
    public function create(Student $student, int $toSectionId, int $toLevelId, ?string $reason, User $actor): StudentTransferRequest
    {
        $this->assertCanCreate($student, $toSectionId, $toLevelId);

        $student->loadMissing('section');
        $section = Section::query()->findOrFail($toSectionId);

        [$yearId, $semester] = $this->termInScope($student);

        return StudentTransferRequest::create([
            'student_id' => $student->id,
            'from_department_id' => $student->departmentId(),
            'from_section_id' => $student->section_id,
            'from_level_id' => $student->level_id,
            'to_department_id' => $section->department_id,
            'to_section_id' => $section->id,
            'to_level_id' => $toLevelId,
            'year_id' => $yearId,
            'semester' => $semester,
            'status' => TransferRequestStatus::PENDING,
            'reason' => $reason,
            'created_by_user_id' => $actor->id,
        ]);
    }

    /**
     * The term the transfer will unwind. Course registrations and fee tickets are keyed
     * by the open academic term, so that is what a transfer has to reverse; the student's
     * own year and semester are only a fallback for when no term is open.
     *
     * @return array{0: ?int, 1: ?Semester}
     */
    protected function termInScope(Student $student): array
    {
        $year = Year::current();
        $semester = $year?->getCurrentSemester();

        if ($year && $semester) {
            return [$year->id, $semester];
        }

        return [$student->year_id, $student->semester];
    }

    /**
     * Everything the approver needs to see before deciding: what will be cancelled,
     * what will be refunded, and how the two departments price the same student.
     *
     * Recomputed on every view while the request is pending, so changes made after the
     * request was raised still show up. Once approved, the stored snapshot is used instead.
     *
     * @return array{registrations: array<int, array<string, mixed>>, paid_tickets: array<int, array<string, mixed>>, pending_tickets: array<int, array<string, mixed>>, registration_refund: float, ticket_refund: float, total_refund: float, wallet_balance_before: float, wallet_balance_after: float, from_fee_setting: ?array<string, float>, to_fee_setting: ?array<string, float>, warnings: array<int, string>}
     */
    public function preview(StudentTransferRequest $request): array
    {
        if ($request->status === TransferRequestStatus::APPROVED && $request->reversal_snapshot) {
            return $request->reversal_snapshot;
        }

        $request->loadMissing('student');
        $student = $request->student;

        $registrations = $this->registrationsInScope($request);
        $tickets = $this->ticketsInScope($request);

        $paidTickets = $tickets->filter(fn (StudentFeeTicket $ticket) => $ticket->isPaid());
        $pendingTickets = $tickets->reject(fn (StudentFeeTicket $ticket) => $ticket->isPaid());

        $registrationRefund = round((float) $registrations->sum(fn (Registration $r) => (float) $r->charged_amount), 2);
        $ticketRefund = round((float) $paidTickets->sum(fn (StudentFeeTicket $t) => (float) $t->amount), 2);
        $totalRefund = round($registrationRefund + $ticketRefund, 2);

        $balance = $this->walletService->getBalance($student);

        return [
            'registrations' => $registrations->map(fn (Registration $registration) => [
                'id' => $registration->id,
                'semester' => $registration->semester?->label(),
                'status' => $registration->status?->label(),
                'charged_amount' => (float) $registration->charged_amount,
                'courses' => $registration->courses->map(fn ($registrationCourse) => [
                    'code' => $registrationCourse->course?->code,
                    'name' => $registrationCourse->course?->name,
                    'hours' => (int) ($registrationCourse->course?->hours ?? 0),
                ])->values()->all(),
            ])->values()->all(),

            'paid_tickets' => $paidTickets->map(fn (StudentFeeTicket $ticket) => [
                'id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'fee_name' => $ticket->fee_name,
                'amount' => (float) $ticket->amount,
                'paid_at' => $ticket->paid_at?->format('Y-m-d'),
            ])->values()->all(),

            'pending_tickets' => $pendingTickets->map(fn (StudentFeeTicket $ticket) => [
                'id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'fee_name' => $ticket->fee_name,
                'amount' => (float) $ticket->amount,
            ])->values()->all(),

            'registration_refund' => $registrationRefund,
            'ticket_refund' => $ticketRefund,
            'total_refund' => $totalRefund,
            'wallet_balance_before' => $balance,
            'wallet_balance_after' => round($balance + $totalRefund, 2),
            'from_fee_setting' => $this->feeSettingFor($request->from_department_id, $request->from_level_id),
            'to_fee_setting' => $this->feeSettingFor($request->to_department_id, $request->to_level_id),
            'warnings' => $this->warningsFor($request),
        ];
    }

    /**
     * Unwind the term, move the student, and freeze what happened onto the request.
     *
     * @return array{refunded: float}
     */
    public function approve(StudentTransferRequest $request, User $actor): array
    {
        $this->assertPending($request);

        $snapshot = $this->preview($request);

        DB::transaction(function () use ($request, $actor, $snapshot) {
            $student = $request->student;

            foreach ($this->registrationsInScope($request) as $registration) {
                $this->billingService->refundAll($registration, $actor);

                $registration->forceFill(['status' => RegistrationStatus::CANCELLED])->save();
            }

            foreach ($this->ticketsInScope($request) as $ticket) {
                if ($ticket->isPaid()) {
                    $this->walletService->refund(
                        student: $student,
                        amount: (float) $ticket->amount,
                        yearId: $ticket->year_id ?? $request->year_id,
                        semester: $ticket->semester ?? $request->semester,
                        reason: 'استرداد حافظة مصاريف بعد تحويل التخصص',
                        reference: $ticket,
                        performedBy: $actor,
                    );
                }

                $ticket->forceFill(['status' => 'cancelled'])->save();
            }

            $student->forceFill([
                'section_id' => $request->to_section_id,
                'level_id' => $request->to_level_id,
            ])->save();

            $request->forceFill([
                'status' => TransferRequestStatus::APPROVED,
                'refunded_amount' => $snapshot['total_refund'],
                'reversal_snapshot' => $snapshot,
                'decided_by_user_id' => $actor->id,
                'decided_at' => now(),
            ])->save();
        });

        return ['refunded' => (float) $snapshot['total_refund']];
    }

    /**
     * Close the request without touching money, courses, or the student.
     */
    public function reject(StudentTransferRequest $request, User $actor, string $reason): void
    {
        $this->assertPending($request);

        $request->forceFill([
            'status' => TransferRequestStatus::REJECTED,
            'rejection_reason' => $reason,
            'decided_by_user_id' => $actor->id,
            'decided_at' => now(),
        ])->save();
    }

    protected function assertPending(StudentTransferRequest $request): void
    {
        if (! $request->isPending()) {
            throw new TransferRequestException('تم البت في هذا الطلب بالفعل.');
        }
    }

    /**
     * Registrations the transfer will unwind: the requested term only, and only those
     * that still stand. An already cancelled or rejected registration owes nothing.
     *
     * @return Collection<int, Registration>
     */
    protected function registrationsInScope(StudentTransferRequest $request): Collection
    {
        return Registration::query()
            ->where('student_id', $request->student_id)
            ->where('year_id', $request->year_id)
            ->where('semester', $request->semester?->value)
            ->whereIn('status', [RegistrationStatus::PENDING->value, RegistrationStatus::APPROVED->value])
            ->with('courses.course')
            ->get();
    }

    /**
     * Fee tickets the transfer will unwind: the requested term only, excluding tickets
     * that are already cancelled.
     *
     * @return Collection<int, StudentFeeTicket>
     */
    protected function ticketsInScope(StudentTransferRequest $request): Collection
    {
        return StudentFeeTicket::query()
            ->where('student_id', $request->student_id)
            ->where('year_id', $request->year_id)
            ->where('semester', $request->semester?->value)
            ->whereIn('status', ['pending', 'paid'])
            ->oldest()
            ->get();
    }

    /**
     * @return ?array{hour_payment: float, ministerial_payment: float}
     */
    protected function feeSettingFor(?int $departmentId, ?int $levelId): ?array
    {
        if (! $departmentId || ! $levelId) {
            return null;
        }

        $feeSetting = \App\Models\RegistrationFee::query()
            ->where('department_id', $departmentId)
            ->where('level_id', $levelId)
            ->first();

        if (! $feeSetting) {
            return null;
        }

        return [
            'hour_payment' => (float) $feeSetting->hour_payment,
            'ministerial_payment' => (float) $feeSetting->ministerial_payment,
        ];
    }

    /**
     * Things the approver should know but that do not block the transfer.
     *
     * @return array<int, string>
     */
    protected function warningsFor(StudentTransferRequest $request): array
    {
        $warnings = [];

        if (! $this->feeSettingFor($request->to_department_id, $request->to_level_id)) {
            $warnings[] = 'لا توجد مصاريف تسجيل مُعرّفة للتخصص والفرقة الجديدة. لن يستطيع الطالب تسجيل مواد قبل ضبطها.';
        }

        $laterRegistrations = Registration::query()
            ->where('student_id', $request->student_id)
            ->whereIn('status', [RegistrationStatus::PENDING->value, RegistrationStatus::APPROVED->value])
            ->when($request->year_id, fn ($query) => $query->where('year_id', '>', $request->year_id))
            ->count();

        if ($laterRegistrations > 0) {
            $warnings[] = "يوجد {$laterRegistrations} سجل تسجيل في أعوام لاحقة لن يتم المساس بها.";
        }

        if (! $request->year_id || ! $request->semester) {
            $warnings[] = 'الطالب غير مرتبط بعام دراسي أو ترم حالي، لذلك لن يتم استرجاع أي مواد أو حوافظ.';
        }

        return $warnings;
    }
}
