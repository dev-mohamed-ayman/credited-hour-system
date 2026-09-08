# Contract: Finance Integration (`DiscountService` × FeeIssuance × FeePayment × Reports)

**Feature**: Student Discounts | The money-critical seam. `DiscountService` owns all discount math and lifecycle; existing finance components call it and only orchestrate (constitution II). Research refs: R2–R7.

## `App\Services\DiscountService` — Public Contract

```php
/** Active, in-scope discounts for a student+fee at issuance (no expiry — R1). */
public function eligibleFor(
    Student $student, DiscountScope|string $feeType, ?int $feeId = null,
    ?int $yearId = null, ?Semester $semester = null,
): Collection;

/** Pure allocation: oldest-first, capped at original, half-up rounding (R5). No DB writes.
 *  Zero-value fee + percentage ⇒ no allocation; the CALLER logs a system notice (FR-011).
 *  @return array{applied: array<int,string>, net: string}  // [discountId => amount] */
public function planApplication(Collection $discounts, string $originalAmount): array;

/** Persist allocation on a ticket — DB::transaction + lockForUpdate per discount (R3/R4).
 *  Writes original/discount/net on ticket + usage rows + discount status/remaining. */
public function applyToTicket(StudentFeeTicket $ticket, array $applied, User $performedBy): void;

/** Explicit re-price of a pending ticket (R7). Refuses paid tickets. Logs `edited`. */
public function applyToPendingTicket(StudentDiscount $discount, StudentFeeTicket $ticket, User $user): void;

/** Grant (immediately active, no approval — Q4). Writes `granted` event. */
public function grant(array $data, User $actor): StudentDiscount;

/** Edit only when active && zero usages (R9). Writes `edited` event with old/new. */
public function update(StudentDiscount $discount, array $data, User $actor): StudentDiscount;

/** Revoke remaining balance only; paid applications untouched (R1). Requires reason. */
public function revoke(StudentDiscount $discount, string $reason, ?User $actor): void;
```

- Money math uses centi-EGP integers / `decimal:2` strings; **no floats** (FR-019).
- Domain violations throw typed exceptions (`DiscountRevokeException`, insufficient-remainder), never string messages (constitution I).
- Every mutation runs inside `DB::transaction`.

## `FeeIssuance` (edit — R4)

| Point | Contract |
|-------|----------|
| After `loadFees()` | For each fee line call `eligibleFor()`; render "خصم متاح" badge + expected net (reactive, `wire:model.live`); pre-generation summary: إجمالي أصلي / إجمالي خصومات / صافي |
| Inside `generateTickets()` existing `DB::transaction` | After each `StudentFeeTicket::create`, call `planApplication()` then `applyToTicket()` **in the same transaction** — a ticket can never exist without its discount |
| No discount present | `original_amount = null`, `discount_amount = 0`, `amount` unchanged (zero regression) |

## `FeePayment` (edit — R6)

| Point | Contract |
|-------|----------|
| Ticket display | show `original_amount` / `discount_amount` / `amount` (net) instead of `amount` alone |
| Ministerial receipt check | skipped **only** when all selected registration tickets have `amount == 0`; positive tickets still consume receipt numbers |
| Zero-net confirmation | manual cashier action sets `status=paid`, `paid_at`, note "سداد بخصم كامل"; **no auto-closure** (Q3) |
| Wallet deposit guard | wrap existing deposit call (`FeePayment.php` ~line 217) in `if ($ticket->amount > 0)` — never create a 0-value `WalletTransaction` |
| BR-13 | confirmed zero-net ticket is `status=paid` ⇒ auto-excluded by `scopeUnpaid` from `outstandingTotal`/`hasOutstandingFees`/`checkFeeGate` — regression test required |

## Reports & Print (edit — FR-012, FR-023, BR-6)

| Surface | Contract |
|---------|----------|
| `print-tickets.blade.php` | receipt line "خصم: {discount_amount} ج.م — السبب: {reason}" when `discount_amount > 0` |
| `StudentFinancialStatus` | four figures: original dues (`Σ original_amount ?? amount`), total discounts, paid, remaining — all read from ticket columns, never recomputed |
| `DailyPayments` | discount column from `ticket.discount_amount` |
| Discounts report (FR-022) | filterable by period/department/year/student; per-row: student, type, applied amount, reason, decision number, granter, application date; totals derived from `student_discount_usages` + ticket snapshots |

## `WalletService` (unchanged — FR-004, Q5)

- Gift balances remain `WalletService::deposit()` only; no `StudentDiscount` is ever created for the legacy "محفظة" type.
- `RegistrationBillingService` (credit-hour settle) is **out of scope** (Q6) — discounts never touch wallet-settled course fees.

## Auto-Revocation Hooks (FR-018, R12)

- `Student::booted()` `deleted` event (soft delete) → revoke all active/partially-applied discounts with reason "حذف الطالب", system actor (`user_id = null` on the `auto_revoked` event).
- `StudentTransferService::approve()` → same sweep, reason "تحويل الطالب", inside the existing transfer transaction.
- Exhausted/revoked rows untouched (history preserved).
- **v1 surfacing**: revocations appear in the discounts screen audit history (T045 timeline) — the repo has no notification infrastructure, and none is introduced (analyze finding C1).
