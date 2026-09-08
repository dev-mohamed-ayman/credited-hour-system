<?php

namespace App\Services;

use App\Enums\DiscountEventAction;
use App\Enums\DiscountMode;
use App\Enums\DiscountScope;
use App\Enums\DiscountStatus;
use App\Enums\Semester;
use App\Exceptions\DiscountEditException;
use App\Exceptions\DiscountInvalidException;
use App\Exceptions\DiscountRevokeException;
use App\Models\Student;
use App\Models\StudentDiscount;
use App\Models\StudentDiscountUsage;
use App\Models\StudentFeeTicket;
use App\Models\User;
use App\Models\Year;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DiscountService
{
    /**
     * Grant a documented discount. Fixed discounts initialize their drawdown
     * balance to the full value; percentages carry none (Q2). A null actor
     * marks system-originated grants (legacy migration).
     *
     * @param  array<string, mixed>  $data
     */
    public function grant(array $data, ?User $actor = null): StudentDiscount
    {
        $mode = $data['mode'] instanceof DiscountMode ? $data['mode'] : DiscountMode::from($data['mode']);

        $this->assertValidDefinition($mode, $data['value'] ?? null, $data['reason'] ?? null);

        return DB::transaction(function () use ($data, $actor, $mode) {
            $discount = StudentDiscount::create([
                ...collect($data)->except(['mode'])->all(),
                'mode' => $mode,
                'status' => DiscountStatus::Active,
                'remaining_amount' => $mode === DiscountMode::Fixed ? (string) $data['value'] : null,
                'created_by' => $actor?->id,
            ]);

            $discount->events()->create([
                'action' => DiscountEventAction::Granted,
                'user_id' => $actor?->id,
                'meta' => ['source' => $data['source'] ?? 'manual'],
            ]);

            return $discount;
        });
    }

    /**
     * Discounts eligible for a fee line right now: not revoked nor exhausted,
     * in scope, and (for fixed) still carrying balance. Oldest grant first.
     */
    public function eligibleFor(
        Student $student,
        string $feeType,
        ?int $feeId = null,
        ?int $yearId = null,
        Semester|string|null $semester = null,
    ): Collection {
        return $student->discounts()
            ->whereIn('status', [DiscountStatus::Active->value, DiscountStatus::PartiallyApplied->value])
            ->get()
            ->filter(function (StudentDiscount $discount) use ($feeType, $feeId, $yearId, $semester) {
                if ($discount->mode === DiscountMode::Fixed && (float) $discount->remaining_amount <= 0) {
                    return false;
                }

                return $discount->appliesTo($feeType, $feeId, $yearId, $semester);
            })
            ->sortBy([['created_at', 'asc'], ['id', 'asc']])
            ->values();
    }

    /**
     * Pure allocation over a ticket's original amount: oldest-first, capped,
     * half-up rounding on percentages, centi-EGP integers only (no floats).
     *
     * @return array{applied: array<int,string>, net: string, notices: array<int,string>}
     */
    public function planApplication(Collection $discounts, string $originalAmount): array
    {
        $originalCents = $this->toCents($originalAmount);
        $unapplied = $originalCents;
        $applied = [];
        $notices = [];

        $ordered = $discounts->sortBy([['created_at', 'asc'], ['id', 'asc']]);

        foreach ($ordered as $discount) {
            if ($discount->mode === DiscountMode::Percentage) {
                if ($originalCents === 0) {
                    $notices[] = "خصم نسبة رقم {$discount->id} لم يُطبَّق: قيمة الرسم صفر.";

                    continue;
                }

                if ($unapplied === 0) {
                    continue;
                }

                $percentCents = $this->toCents((string) $discount->value);
                $cents = intdiv($originalCents * $percentCents + 5000, 10000);
            } else {
                if ($unapplied === 0) {
                    continue;
                }

                $cents = min($this->toCents((string) $discount->remaining_amount), $unapplied);
            }

            $cents = min($cents, $unapplied);

            if ($cents <= 0) {
                continue;
            }

            $applied[$discount->id] = $this->fromCents($cents);
            $unapplied -= $cents;
        }

        return ['applied' => $applied, 'net' => $this->fromCents($unapplied), 'notices' => $notices];
    }

    /**
     * Surface allocation notices (e.g. percentage on a zero-value fee) in the log.
     *
     * @param  array<int,string>  $notices
     */
    public function logApplicationNotices(array $notices): void
    {
        foreach ($notices as $notice) {
            Log::info('خصومات الطلاب: '.$notice);
        }
    }

    /**
     * Persist a planned allocation onto a ticket: row-locked per discount so
     * concurrent issuances can never overspend the same balance (BR-4).
     *
     * @param  array<int,string>  $applied  [discountId => amount]
     */
    public function applyToTicket(StudentFeeTicket $ticket, array $applied, ?User $performedBy = null): void
    {
        if (empty($applied)) {
            return;
        }

        DB::transaction(function () use ($ticket, $applied, $performedBy) {
            $grossCents = $this->toCents((string) ($ticket->original_amount ?? $ticket->amount));

            foreach ($applied as $discountId => $plannedAmount) {
                $discount = StudentDiscount::whereKey($discountId)->lockForUpdate()->first();

                if (! $discount
                    || $discount->status === DiscountStatus::Revoked
                    || $discount->status === DiscountStatus::Exhausted) {
                    continue;
                }

                if ($ticket->discountUsages()->where('student_discount_id', $discount->id)->exists()) {
                    continue;
                }

                $plannedCents = $this->toCents((string) $plannedAmount);

                if ($discount->mode === DiscountMode::Fixed) {
                    $plannedCents = min($plannedCents, $this->toCents((string) $discount->remaining_amount));
                }

                $plannedCents = min($plannedCents, $grossCents);

                if ($plannedCents <= 0) {
                    continue;
                }

                $ticket->discountUsages()->create([
                    'student_discount_id' => $discount->id,
                    'applied_amount' => $this->fromCents($plannedCents),
                    'applied_by' => $performedBy?->id,
                ]);

                if ($discount->mode === DiscountMode::Fixed) {
                    $remainingCents = $this->toCents((string) $discount->remaining_amount) - $plannedCents;
                    $discount->forceFill([
                        'remaining_amount' => $this->fromCents(max(0, $remainingCents)),
                        'status' => $remainingCents <= 0 ? DiscountStatus::Exhausted : DiscountStatus::PartiallyApplied,
                    ])->save();
                }
            }

            $totalCents = $this->toCents((string) $ticket->discountUsages()->sum('applied_amount'));

            $ticket->update([
                'original_amount' => $this->fromCents($grossCents),
                'discount_amount' => $this->fromCents($totalCents),
                'amount' => $this->fromCents(max(0, $grossCents - $totalCents)),
            ]);
        });
    }

    /**
     * Revoke only the unused remainder of a discount (R1). Applications already
     * recorded — including those on now-paid tickets — are immutable history.
     */
    public function revoke(StudentDiscount $discount, string $reason, ?User $actor): void
    {
        if (trim($reason) === '') {
            throw new DiscountRevokeException('سبب الإلغاء إلزامي — لا يمكن إلغاء خصم بدون توثيق.');
        }

        DB::transaction(function () use ($discount, $reason, $actor) {
            $locked = StudentDiscount::whereKey($discount->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === DiscountStatus::Revoked) {
                throw new DiscountRevokeException('هذا الخصم ملغى بالفعل.');
            }

            if ($locked->status === DiscountStatus::Exhausted) {
                throw new DiscountRevokeException('لا يوجد رصيد متبقٍ للإلغاء — الخصم مطبَّق بالكامل، والتصحيح يكون بتسوية يدوية موثقة.');
            }

            if ($locked->mode === DiscountMode::Fixed && $this->toCents((string) $locked->remaining_amount) <= 0) {
                throw new DiscountRevokeException('لا يوجد رصيد متبقٍ للإلغاء — الخصم مطبَّق بالكامل، والتصحيح يكون بتسوية يدوية موثقة.');
            }

            $locked->forceFill([
                'status' => DiscountStatus::Revoked,
                'revoked_by' => $actor?->id,
                'revoked_at' => now(),
                'revoked_reason' => $reason,
            ])->save();

            $locked->events()->create([
                'action' => $actor ? DiscountEventAction::Revoked : DiscountEventAction::AutoRevoked,
                'user_id' => $actor?->id,
                'meta' => ['reason' => $reason, 'remaining_cancelled' => $locked->mode === DiscountMode::Fixed ? (string) $locked->remaining_amount : null],
            ]);
        });
    }

    /**
     * Revoke every still-usable discount of a student (R12) — deletion/transfer sweep.
     *
     * @return int number of revoked discounts
     */
    public function revokeActiveForStudent(Student $student, string $reason, ?User $actor = null): int
    {
        $count = 0;

        $student->discounts()
            ->whereIn('status', [DiscountStatus::Active->value, DiscountStatus::PartiallyApplied->value])
            ->get()
            ->each(function (StudentDiscount $discount) use ($reason, $actor, &$count) {
                try {
                    $this->revoke($discount, $reason, $actor);
                    $count++;
                } catch (DiscountRevokeException) {
                    // raced to exhausted/revoked concurrently — nothing left to cancel
                }
            });

        return $count;
    }

    /**
     * Edit an untouched active discount only (R9); applied discounts are immutable.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(StudentDiscount $discount, array $data, User $actor): StudentDiscount
    {
        DB::transaction(function () use ($discount, $data, $actor) {
            $locked = StudentDiscount::whereKey($discount->id)->lockForUpdate()->firstOrFail();

            $rawMode = $data['mode'] ?? $locked->mode;
            $mode = $rawMode instanceof DiscountMode ? $rawMode : DiscountMode::from($rawMode);

            $this->assertValidDefinition($mode, $data['value'] ?? $locked->value, $data['reason'] ?? $locked->reason);

            if ($locked->status !== DiscountStatus::Active || $locked->usages()->exists()) {
                throw new DiscountEditException('لا يمكن تعديل خصم بعد تطبيقه — ألغه وأعد منحه.');
            }

            $editable = collect($data)->only(['scope', 'fee_id', 'year_id', 'semester', 'mode', 'value', 'reason', 'decision_number'])->all();

            $old = collect($locked->only(array_keys($editable)))
                ->map(fn ($v) => $v instanceof \BackedEnum ? $v->value : (is_numeric($v) ? (string) $v : $v))
                ->all();

            $locked->forceFill([
                ...$editable,
                'remaining_amount' => $mode === DiscountMode::Fixed ? (string) ($data['value'] ?? $locked->value) : null,
            ])->save();

            $new = collect($locked->only(array_keys($editable)))
                ->map(fn ($v) => $v instanceof \BackedEnum ? $v->value : (is_numeric($v) ? (string) $v : $v))
                ->all();

            $locked->events()->create([
                'action' => DiscountEventAction::Edited,
                'user_id' => $actor->id,
                'meta' => ['old' => $old, 'new' => $new],
            ]);
        });

        return $discount->refresh();
    }

    /**
     * Explicit re-pricing of a pending ticket after a discount was granted (R7).
     * Paid or cancelled tickets are never touched.
     */
    public function applyToPendingTicket(StudentDiscount $discount, StudentFeeTicket $ticket, User $actor): void
    {
        if (! $ticket->isPending()) {
            throw new DiscountEditException('لا يمكن إعادة تسعير حافظة غير معلنة (مدفوعة أو ملغاة).');
        }

        DB::transaction(function () use ($discount, $ticket, $actor) {
            if (in_array($discount->status, [DiscountStatus::Revoked, DiscountStatus::Exhausted], true)
                || ($discount->mode === DiscountMode::Fixed && $this->toCents((string) $discount->remaining_amount) <= 0)) {
                throw new DiscountEditException('لا يمكن تطبيق خصم ملغى أو مستنفد على هذه الحافظة.');
            }

            if (! $discount->appliesTo(
                $ticket->fee_type,
                $ticket->fee_id ? (int) $ticket->fee_id : null,
                $ticket->year_id,
                $ticket->semester,
            )) {
                throw new DiscountEditException('هذا الخصم لا ينطاق على هذه الحافظة أو استُنفد رصيده.');
            }

            if ($ticket->discountUsages()->where('student_discount_id', $discount->id)->exists()) {
                throw new DiscountEditException('هذا الخصم مطبَّق بالفعل على هذه الحافظة.');
            }

            $eligible = $this->eligibleFor(
                $ticket->student,
                $ticket->fee_type,
                $ticket->fee_id ? (int) $ticket->fee_id : null,
                $ticket->year_id,
                $ticket->semester,
            );

            $gross = (string) ($ticket->original_amount ?? $ticket->amount);
            $plan = $this->planApplication($eligible, $gross);

            $this->applyToTicket($ticket, $plan['applied'], $actor);

            $discount->events()->create([
                'action' => DiscountEventAction::Edited,
                'user_id' => $actor->id,
                'meta' => [
                    'source' => 're-price-pending',
                    'ticket_id' => $ticket->id,
                    'net_after' => (string) $ticket->fresh()->amount,
                ],
            ]);
        });
    }

    /**
     * All-or-nothing bulk import from the legacy 4-column CSV
     * [student code, fee type, discount amount, discount reason]. Fixed-amount,
     * current-term discounts only (clarification R11). Any invalid row rejects
     * the whole file with row-level errors.
     *
     * @return array{success: bool, message: string, imported: int, errors: array<int,string>}
     */
    public function importCsv(string $path, User $actor): array
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return ['success' => false, 'message' => 'تعذر قراءة الملف', 'imported' => 0, 'errors' => []];
        }

        $rows = [];
        $line = 0;

        while (($cells = fgetcsv($handle)) !== false) {
            $line++;
            $cells = array_map(fn ($c) => trim((string) $c), $cells);

            if ($line === 1) {
                $cells[0] = preg_replace('/^\xEF\xBB\xBF/', '', $cells[0]);
                if (count($cells) < 4) {
                    fclose($handle);

                    return ['success' => false, 'message' => 'الترويسة يجب أن تحتوي على 4 أعمدة', 'imported' => 0, 'errors' => []];
                }

                continue;
            }

            if (implode('', $cells) === '') {
                continue;
            }

            $rows[$line] = $cells;
        }

        fclose($handle);

        if (empty($rows)) {
            return ['success' => false, 'message' => 'لا توجد بيانات في الملف', 'imported' => 0, 'errors' => ['لا توجد صفوف خصم في الملف.']];
        }

        $currentYear = Year::current();
        $currentSemester = Year::currentSemester();
        $errors = [];
        $prepared = [];

        foreach ($rows as $rowNumber => [$code, $feeType, $amount, $reason]) {
            $student = Student::where('username', $code)->first();

            if (! $student) {
                $errors[] = "السطر {$rowNumber}: لا يوجد طالب بالكود «{$code}».";

                continue;
            }

            if (! is_numeric($amount) || (float) $amount <= 0) {
                $errors[] = "السطر {$rowNumber}: قيمة الخصم يجب أن تكون رقمًا أكبر من صفر.";

                continue;
            }

            if (trim((string) $reason) === '') {
                $errors[] = "السطر {$rowNumber}: سبب الخصم إلزامي.";

                continue;
            }

            $prepared[] = [
                'student_id' => $student->id,
                'scope' => DiscountScope::Registration->value,
                'year_id' => $currentYear?->id,
                'semester' => $currentSemester?->value,
                'mode' => DiscountMode::Fixed->value,
                'value' => (string) $amount,
                'reason' => $reason,
                'source' => 'csv',
            ];
        }

        if ($errors !== []) {
            return ['success' => false, 'message' => 'الملف مرفوض بالكامل — لم يُستورد أي خصم', 'imported' => 0, 'errors' => $errors];
        }

        DB::transaction(function () use ($prepared, $actor) {
            foreach ($prepared as $data) {
                $this->grant($data, $actor);
            }
        });

        return [
            'success' => true,
            'message' => 'تم استيراد '.count($prepared).' خصمًا بنجاح',
            'imported' => count($prepared),
            'errors' => [],
        ];
    }

    /**
     * Undo every discount application recorded against a ticket that is about
     * to be destroyed: balances are restored, usage rows deleted, and each
     * discount gets an audit event. Without this, deleting a discounted
     * pending ticket would silently consume discount balance (invariants 2/3).
     */
    public function revertTicket(StudentFeeTicket $ticket, ?User $actor = null): void
    {
        DB::transaction(function () use ($ticket, $actor) {
            $usages = StudentDiscountUsage::query()
                ->where('student_fee_ticket_id', $ticket->id)
                ->lockForUpdate()
                ->get();

            if ($usages->isEmpty()) {
                return;
            }

            foreach ($usages->groupBy('student_discount_id') as $discountId => $rows) {
                $discount = StudentDiscount::whereKey($discountId)->lockForUpdate()->first();

                if ($discount === null) {
                    continue;
                }

                $restoredCents = (int) $rows->sum(fn ($usage) => $this->toCents((string) $usage->applied_amount));

                if ($discount->mode === DiscountMode::Fixed) {
                    $newRemaining = min(
                        $this->toCents((string) $discount->value),
                        $this->toCents((string) $discount->remaining_amount) + $restoredCents
                    );

                    StudentDiscountUsage::whereIn('id', $rows->pluck('id'))->delete();

                    $hasOtherUsages = $discount->usages()->exists();

                    $status = match (true) {
                        $discount->status === DiscountStatus::Revoked => DiscountStatus::Revoked,
                        $newRemaining >= $this->toCents((string) $discount->value) && ! $hasOtherUsages => DiscountStatus::Active,
                        $newRemaining > 0 => DiscountStatus::PartiallyApplied,
                        default => DiscountStatus::Exhausted,
                    };

                    $discount->forceFill([
                        'remaining_amount' => $this->fromCents($newRemaining),
                        'status' => $status,
                    ])->save();
                } else {
                    StudentDiscountUsage::whereIn('id', $rows->pluck('id'))->delete();
                }

                $discount->events()->create([
                    'action' => DiscountEventAction::Edited,
                    'user_id' => $actor?->id,
                    'meta' => [
                        'source' => 'ticket-reverted',
                        'ticket_id' => $ticket->id,
                        'restored_amount' => $this->fromCents($restoredCents),
                    ],
                ]);
            }
        });
    }

    /**
     * Integrity invariants every definition must satisfy regardless of caller
     * (UI, CSV, legacy migration) — data-model.md validation table.
     */
    private function assertValidDefinition(DiscountMode $mode, mixed $value, mixed $reason): void
    {
        if (! is_numeric($value) || (float) $value <= 0) {
            throw new DiscountInvalidException('قيمة الخصم يجب أن تكون رقمًا أكبر من صفر.');
        }

        if ($mode === DiscountMode::Percentage && (float) $value > 100) {
            throw new DiscountInvalidException('النسبة المئوية لا يمكن أن تتجاوز 100٪.');
        }

        if (trim((string) $reason) === '') {
            throw new DiscountInvalidException('سبب الخصم إلزامي للتوثيق.');
        }
    }

    private function toCents(string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    private function fromCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
