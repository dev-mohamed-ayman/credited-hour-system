<?php

namespace App\Services;

use App\Enums\Semester;
use App\Enums\WalletTransactionType;
use App\Models\Registration;
use App\Models\RegistrationFee;
use App\Models\Student;
use App\Models\StudentWallet;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class WalletService
{
    /**
     * Get the student's wallet balance.
     */
    public function getBalance(Student $student): float
    {
        return (float) $student->wallet()->value('balance') ?? 0.0;
    }

    /**
     * Deposit an amount to the student's wallet.
     */
    public function deposit(
        Student $student,
        float $amount,
        int $yearId,
        Semester|string $semester,
        string $reason,
        ?Model $reference = null,
        ?Model $performedBy = null
    ): WalletTransaction {
        return DB::transaction(function () use ($student, $amount, $yearId, $semester, $reason, $reference, $performedBy) {
            $wallet = StudentWallet::firstOrCreate(
                ['student_id' => $student->id],
                ['balance' => 0]
            );

            $wallet->increment('balance', $amount);

            return $student->walletTransactions()->create([
                'year_id' => $yearId,
                'semester' => $semester instanceof Semester ? $semester->value : $semester,
                'amount' => $amount,
                'type' => WalletTransactionType::DEPOSIT,
                'reason' => $reason,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'performed_by_type' => $performedBy?->getMorphClass(),
                'performed_by_id' => $performedBy?->getKey(),
            ]);
        });
    }

    /**
     * Withdraw an amount from the student's wallet.
     * Throws an exception if the balance is insufficient.
     */
    public function withdraw(
        Student $student,
        float $amount,
        int $yearId,
        Semester|string $semester,
        string $reason,
        ?Model $reference = null,
        ?Model $performedBy = null
    ): WalletTransaction {
        if (! $this->hasEnoughBalance($student, $amount)) {
            throw new \Exception('رصيد المحفظة غير كافٍ.');
        }

        return DB::transaction(function () use ($student, $amount, $yearId, $semester, $reason, $reference, $performedBy) {
            $wallet = $student->wallet;

            $wallet->decrement('balance', $amount);

            return $student->walletTransactions()->create([
                'year_id' => $yearId,
                'semester' => $semester instanceof Semester ? $semester->value : $semester,
                'amount' => $amount,
                'type' => WalletTransactionType::WITHDRAWAL,
                'reason' => $reason,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'performed_by_type' => $performedBy?->getMorphClass(),
                'performed_by_id' => $performedBy?->getKey(),
            ]);
        });
    }

    /**
     * Give an amount back to the student's wallet. Recorded as a refund rather than a
     * deposit so reversals stay distinguishable from money the student actually paid in.
     */
    public function refund(
        Student $student,
        float $amount,
        int $yearId,
        Semester|string $semester,
        string $reason,
        ?Model $reference = null,
        ?Model $performedBy = null
    ): WalletTransaction {
        return DB::transaction(function () use ($student, $amount, $yearId, $semester, $reason, $reference, $performedBy) {
            $wallet = StudentWallet::firstOrCreate(
                ['student_id' => $student->id],
                ['balance' => 0]
            );

            $wallet->increment('balance', $amount);

            return $student->walletTransactions()->create([
                'year_id' => $yearId,
                'semester' => $semester instanceof Semester ? $semester->value : $semester,
                'amount' => $amount,
                'type' => WalletTransactionType::REFUND,
                'reason' => $reason,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'performed_by_type' => $performedBy?->getMorphClass(),
                'performed_by_id' => $performedBy?->getKey(),
            ]);
        });
    }

    /**
     * The registration fee settings that apply to a student's department and level.
     */
    public function feeSettingFor(Student $student): ?RegistrationFee
    {
        $student->loadMissing(['section.department', 'level']);

        return RegistrationFee::query()
            ->where('department_id', $student->departmentId())
            ->where('level_id', $student->level_id)
            ->first();
    }

    /**
     * Check if the student has enough balance.
     */
    public function hasEnoughBalance(Student $student, float $amount): bool
    {
        return $this->getBalance($student) >= $amount;
    }

    /**
     * Calculate the registration cost for a given registration record.
     */
    public function calculateRegistrationCost(Registration $registration): float
    {
        $registration->loadMissing(['student.section.department', 'student.level', 'courses.course']);

        $feeSetting = $this->feeSettingFor($registration->student);

        if (! $feeSetting) {
            // Default behavior if no fee setting exists
            return 0.0;
        }

        $totalHours = $registration->courses->sum(fn ($rc) => (int) $rc->course->hours);

        $hourPayment = (float) $feeSetting->hour_payment;
        $ministerialPayment = (float) $feeSetting->ministerial_payment;

        return ($totalHours * $hourPayment) + $ministerialPayment;
    }
}
