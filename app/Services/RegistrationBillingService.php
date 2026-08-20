<?php

namespace App\Services;

use App\Enums\RegistrationStatus;
use App\Models\Registration;
use App\Models\Student;
use App\Models\StudentFeeTicket;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Owns every money-touching part of course registration: the outstanding-fee gate
 * that must pass before any course is saved, and the settlement engine that keeps a
 * registration's wallet charge in step with the courses it actually holds.
 */
class RegistrationBillingService
{
    public function __construct(
        protected WalletService $walletService
    ) {}

    /**
     * Fee tickets the student still owes, oldest first.
     *
     * @return Collection<int, StudentFeeTicket>
     */
    public function outstandingTickets(Student $student): Collection
    {
        return StudentFeeTicket::query()
            ->where('student_id', $student->id)
            ->unpaid()
            ->with('year')
            ->oldest()
            ->get();
    }

    public function outstandingTotal(Student $student): float
    {
        return (float) StudentFeeTicket::query()
            ->where('student_id', $student->id)
            ->unpaid()
            ->sum('amount');
    }

    public function hasOutstandingFees(Student $student): bool
    {
        return StudentFeeTicket::query()
            ->where('student_id', $student->id)
            ->unpaid()
            ->exists();
    }

    /**
     * The fee gate. Registration of any kind is refused while the student owes money.
     *
     * @return array{allowed: bool, message: string}
     */
    public function checkFeeGate(Student $student): array
    {
        $tickets = $this->outstandingTickets($student);

        if ($tickets->isEmpty()) {
            return ['allowed' => true, 'message' => ''];
        }

        $total = number_format((float) $tickets->sum('amount'), 2);

        return [
            'allowed' => false,
            'message' => "لا يمكن تسجيل المواد قبل سداد كامل المصاريف المستحقة. عدد الحوافظ غير المدفوعة: {$tickets->count()} بإجمالي {$total} ج.م.",
        ];
    }

    /**
     * Amount this registration should have been charged for the courses it currently holds.
     */
    public function targetCost(Registration $registration): float
    {
        return $this->walletService->calculateRegistrationCost($registration);
    }

    /**
     * Amount still to be taken (positive) or given back (negative) to bring the
     * wallet in line with the registration's current courses.
     */
    public function outstandingDelta(Registration $registration): float
    {
        return round($this->targetCost($registration) - (float) $registration->charged_amount, 2);
    }

    /**
     * Bring the wallet in line with what the registration currently holds.
     *
     * Charges only the difference, so re-running it is harmless: approving twice, or
     * settling an unchanged registration, moves no money. A registration that shrank
     * is refunded the difference. Only approved registrations carry a charge.
     *
     * @return array{success: bool, message: string, delta: float}
     */
    public function settle(Registration $registration, ?Model $performedBy = null): array
    {
        $registration->refresh()->loadMissing(['student', 'courses.course']);

        $delta = $this->outstandingDelta($registration);

        if (abs($delta) < 0.01) {
            return ['success' => true, 'message' => '', 'delta' => 0.0];
        }

        if ($delta > 0 && ! $this->walletService->hasEnoughBalance($registration->student, $delta)) {
            $balance = number_format($this->walletService->getBalance($registration->student), 2);
            $needed = number_format($delta, 2);

            return [
                'success' => false,
                'message' => "رصيد المحفظة غير كافٍ. المطلوب {$needed} ج.م والرصيد الحالي {$balance} ج.م.",
                'delta' => $delta,
            ];
        }

        DB::transaction(function () use ($registration, $delta, $performedBy) {
            if ($delta > 0) {
                $this->walletService->withdraw(
                    student: $registration->student,
                    amount: $delta,
                    yearId: $registration->year_id,
                    semester: $registration->semester,
                    reason: 'خصم رسوم تسجيل مواد',
                    reference: $registration,
                    performedBy: $performedBy,
                );
            } else {
                $this->walletService->refund(
                    student: $registration->student,
                    amount: abs($delta),
                    yearId: $registration->year_id,
                    semester: $registration->semester,
                    reason: 'استرداد رسوم تسجيل مواد',
                    reference: $registration,
                    performedBy: $performedBy,
                );
            }

            $registration->forceFill([
                'charged_amount' => round((float) $registration->charged_amount + $delta, 2),
            ])->save();
        });

        $amount = number_format(abs($delta), 2);
        $message = $delta > 0
            ? "تم خصم {$amount} ج.م من المحفظة."
            : "تم استرداد {$amount} ج.م إلى المحفظة.";

        return ['success' => true, 'message' => $message, 'delta' => $delta];
    }

    /**
     * Give back everything this registration was ever charged. Used when a
     * registration is rejected outright.
     *
     * @return array{success: bool, message: string, delta: float}
     */
    public function refundAll(Registration $registration, ?Model $performedBy = null): array
    {
        $registration->refresh()->loadMissing('student');

        $charged = round((float) $registration->charged_amount, 2);

        if ($charged < 0.01) {
            return ['success' => true, 'message' => '', 'delta' => 0.0];
        }

        DB::transaction(function () use ($registration, $charged, $performedBy) {
            $this->walletService->refund(
                student: $registration->student,
                amount: $charged,
                yearId: $registration->year_id,
                semester: $registration->semester,
                reason: 'استرداد رسوم تسجيل مواد بعد رفض التسجيل',
                reference: $registration,
                performedBy: $performedBy,
            );

            $registration->forceFill(['charged_amount' => 0])->save();
        });

        return [
            'success' => true,
            'message' => 'تم استرداد '.number_format($charged, 2).' ج.م إلى المحفظة.',
            'delta' => -$charged,
        ];
    }

    /**
     * Settle only when the registration is in a state that carries a charge.
     * A pending or rejected registration owes nothing until it is approved.
     *
     * @return array{success: bool, message: string, delta: float}
     */
    public function settleIfApproved(Registration $registration, ?Model $performedBy = null): array
    {
        if ($registration->status !== RegistrationStatus::APPROVED) {
            return ['success' => true, 'message' => '', 'delta' => 0.0];
        }

        return $this->settle($registration, $performedBy);
    }

    /**
     * Breakdown used by the registration screens to show the student what a
     * selection will cost before they commit to it.
     *
     * @param  array<int>  $courseIds
     * @return array{hours: int, hour_payment: float, ministerial_payment: float, total: float, already_charged: float, delta: float, balance: float, balance_after: float, has_fee_setting: bool}
     */
    public function quote(Student $student, ?Registration $registration, array $courseIds): array
    {
        $feeSetting = $this->walletService->feeSettingFor($student);

        $hours = (int) \App\Models\Course::query()->whereIn('id', $courseIds)->sum('hours');
        $alreadyCharged = (float) ($registration->charged_amount ?? 0);

        $existingHours = $registration
            ? (int) $registration->courses()->with('course')->get()->sum(fn ($rc) => (int) $rc->course->hours)
            : 0;

        $hourPayment = (float) ($feeSetting->hour_payment ?? 0);
        $ministerialPayment = (float) ($feeSetting->ministerial_payment ?? 0);

        $total = $feeSetting
            ? (($hours + $existingHours) * $hourPayment) + $ministerialPayment
            : 0.0;

        $balance = $this->walletService->getBalance($student);
        $delta = round($total - $alreadyCharged, 2);

        return [
            'hours' => $hours,
            'existing_hours' => $existingHours,
            'hour_payment' => $hourPayment,
            'ministerial_payment' => $ministerialPayment,
            'total' => $total,
            'already_charged' => $alreadyCharged,
            'delta' => $delta,
            'balance' => $balance,
            'balance_after' => round($balance - $delta, 2),
            'has_fee_setting' => $feeSetting !== null,
        ];
    }
}
