# Phase 1 Data Model: Student Discounts

**Feature**: `specs/003-student-discounts` | **Date**: 2026-09-08
Research references: [research.md](./research.md) (R1–R13). All changes are **additive** — three new tables plus two columns on `student_fee_tickets`; no existing column is altered and no backfill is required.

## Entity Relationship Overview

```text
Student (existing, soft-deletes) ──1:N──► StudentDiscount ◄──N:1── User (granter, nullOnDelete)
       │  auto-revoke hooks (R12)            │ 1
       │                                     ├──1:N──► StudentDiscountEvent (audit: granted/edited/
       │                                     │                        revoked/auto_revoked)  (R8)
       │                                     └──1:N──► StudentDiscountUsage ◄──N:1───┐
AdditionalFee / RegistrationFee (existing, fee_id untyped like tickets) ──scope──┐   │
Year (existing) ──optional scope──►──────────────────────────────────────────────┤   │
                                        StudentFeeTicket (existing, +2 columns) ◄─┘───┘
                                        original_amount / discount_amount / amount(net)
                                        1:N ──► WalletTransaction (existing — zero-value rows
                                        NEVER created; deposit skipped at net 0, R6)
```

## New Tables

### 1. `student_discounts` — migration `create_student_discounts_table`

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | bigIncrements | PK | |
| student_id | foreignId | FK → students, **cascadeOnDelete** | discount owned by exactly one student (BR-1) |
| scope | string | cast `DiscountScope` enum | `registration` / `additional` / `any` |
| fee_id | unsignedBigInteger | nullable, **no FK** | specific fee when `scope=additional`; untyped like `student_fee_tickets.fee_id` (mirrors existing convention) |
| year_id | foreignId | FK → years, nullable, **nullOnDelete** | null ⇒ all years (open scope) |
| semester | string | cast `Semester` enum, nullable | null ⇒ all semesters; **no expiry column** (R1) |
| mode | string | cast `DiscountMode` enum | `fixed` / `percentage` |
| value | decimal(10,2) | required, > 0 | amount in EGP (fixed) or percentage points (percentage, ≤ 100) |
| remaining_amount | decimal(10,2) | nullable | fixed only: drawdown balance (Q1); **initialized to `value` at grant** (T019); null for percentage |
| status | string | cast `DiscountStatus`, default `active` | see state machine below |
| reason | string | required | free text, Arabic UI |
| decision_number | string | nullable | paper-trail reference (Q4: no approval workflow) |
| created_by | foreignId → users | nullable, **nullOnDelete** | granter (audit ref) |
| revoked_by | foreignId → users | nullable, nullOnDelete | revoker |
| revoked_at | timestamp | nullable | |
| revoked_reason | string | nullable | required whenever status=revoked (service-enforced) |
| timestamps | — | `timestamps()` | |

**Indexes**: `index(student_id, status, scope)` — eligibility lookup at issuance (R4/R5).
**No hard delete**: model exposes no delete path; lifecycle is revoke-only (FR-003). No `HasDeletionGuards` needed — deletion is simply not offered.

### 2. `student_discount_usages` — migration `create_student_discount_usages_table`

Append-only application ledger (what made consumption auditable and double-spend impossible).

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | bigIncrements | PK | |
| student_discount_id | foreignId | FK → student_discounts, **cascadeOnDelete** | |
| student_fee_ticket_id | foreignId | FK → student_fee_tickets, **cascadeOnDelete** | |
| applied_amount | decimal(10,2) | required, > 0 | frozen at application time (FR-009) |
| applied_by | foreignId → users | nullable, nullOnDelete | acting staff user |
| timestamps | — | `timestamps()` | |

**Constraints**: `unique(student_discount_id, student_fee_ticket_id)` — DB-level double-spend backstop (R3).

### 3. `student_discount_events` — migration `create_student_discount_events_table`

Audit trail for mutations the discount row itself overwrites (R8).

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | bigIncrements | PK | |
| student_discount_id | foreignId | FK → student_discounts, **cascadeOnDelete** | |
| action | string | cast `DiscountEventAction` enum | `granted` / `edited` / `revoked` / `auto_revoked` |
| meta | json | nullable | old/new values on edit; `{source: csv|legacy-migration}` on grant; reason on revoke |
| user_id | foreignId → users | nullable, nullOnDelete | null ⇒ system actor (R12) |
| timestamps | — | `timestamps()` | |

### 4. `student_fee_tickets` additions — migration `add_discount_columns_to_student_fee_tickets_table`

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| original_amount | decimal(10,2) | nullable, after `amount` | null ⇒ ticket has no discount; gross value when set (R2) |
| discount_amount | decimal(10,2) | default 0, after `original_amount` | total discounted; `original_amount = amount + discount_amount` invariant when non-null |

`amount` **remains the net payable** — all existing consumers (`scopeUnpaid`, `outstandingTotal`, `checkFeeGate`, reports) keep working unchanged; legacy tickets read as `original = amount + 0`.

## Enums (all backed, Arabic `label()`, string-stored)

| Enum | Cases | Labels |
|------|-------|--------|
| `DiscountScope` | `Registration='registration'`, `Additional='additional'`, `Any='any'` | رسوم التسجيل / رسوم إضافية محددة / أي رسوم |
| `DiscountMode` | `Fixed='fixed'`, `Percentage='percentage'` | مبلغ ثابت (ج.م) / نسبة مئوية (٪) |
| `DiscountStatus` | `Active='active'`, `PartiallyApplied='partially_applied'`, `Exhausted='exhausted'`, `Revoked='revoked'` | نشط / مطبَّق جزئيًا / مستنفَد / ملغى (+ `badgeClass()`) |
| `DiscountEventAction` | `Granted='granted'`, `Edited='edited'`, `Revoked='revoked'`, `AutoRevoked='auto_revoked'` | منح / تعديل / إلغاء / إلغاء تلقائي |

## State Machine (`DiscountStatus`)

```text
                     ┌────────────────────────── percentage: stays active (no balance, Q2)
                     │
 active ──apply(fixed, partial)──► partially_applied ──apply(fixed, to 0)──► exhausted
   │                                    │
   │ apply(fixed, full at once)         │ revoke(remaining only; paid
   └────────────────────────────────────┤   applications untouched — R1)
                                        ▼
 active / partially_applied ────revoke(reason required)───► revoked   [terminal]
 exhausted ──(no revoke — nothing remains)──► stays exhausted        [terminal]
```

- Transition writes happen only inside `DiscountService` transactions (constitution I).
- `revoked` requires `revoked_reason` + `revoked_by` + `revoked_at` (or an `auto_revoked` event with `user_id = null` for R12 system paths).
- No transition ever leaves the revoked/exhausted states; no deletes.

## Model Relationships

```php
Student:        hasMany(StudentDiscount)
StudentDiscount:belongsTo(Student); belongsTo(User,'created_by'); belongsTo(User,'revoked_by');
                belongsTo(Year); hasMany(StudentDiscountUsage); hasMany(StudentDiscountEvent)
StudentDiscountUsage:belongsTo(StudentDiscount); belongsTo(StudentFeeTicket); belongsTo(User,'applied_by')
StudentDiscountEvent:belongsTo(StudentDiscount); belongsTo(User)
StudentFeeTicket:hasMany(StudentDiscountUsage)  // + casts original_amount => 'decimal:2', discount_amount => 'decimal:2'
```

## Validation Rules (service + Livewire `rules()`, Arabic `messages()`)

| Rule | Enforcement |
|------|-------------|
| `value > 0`; percentage additionally `value ≤ 100` | grant/edit/CSV |
| `scope=additional` ⇒ `fee_id` required and must reference an `AdditionalFee` | grant/edit |
| `reason` required (1–255 chars); `decision_number` optional text | grant |
| `revoked_reason` required on revoke | revoke |
| Edit only when `status=active` AND zero usages (R9) | `DiscountService::update()` typed exception |
| Revoke only when status ∈ {active, partially_applied}; remainder must be > 0 (R1) | `DiscountService::revoke()` |
| Re-price target must be `status=pending` and in scope (R7) | `applyToPendingTicket()` |
| CSV: exactly 4 normalized headers; per row — student exists (not soft-deleted), fee type known, amount > 0, reason present; any failure ⇒ reject all (R11) | importer pre-validation |
| Money: `decimal:2` casts; percentage results `round(x, 2, PHP_ROUND_HALF_UP)`; internal math in centi-EGP integers (R5, FR-019) | `planApplication()` |

## Derived Invariants (asserted in tests, guaranteed in service transactions)

1. `ticket.original_amount === ticket.amount + ticket.discount_amount` whenever `original_amount` is non-null.
2. `Σ usages.applied_amount(discount) + remaining_amount(fixed) === value` — a fixed discount's balance is fully accounted for.
3. `Σ usages.applied_amount(ticket) === ticket.discount_amount` — the ticket snapshot equals the ledger.
4. `ticket.discount_amount ≤ ticket.original_amount` — never over-discounted (FR-007).
5. Zero `WalletTransaction` rows with amount 0 ever exist (R6).
