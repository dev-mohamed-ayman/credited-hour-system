# Research: Student Discounts

**Feature**: 003-student-discounts | **Date**: 2026-09-08
**Inputs**: [spec.md](./spec.md) (10 clarifications, 2026-09-08), `Student Discounts — Implementation Plan (How).md` (repo-root technical proposal), constitution v1.0.0, delivered code (`StudentFeeTicket`, `FeeIssuance`, `FeePayment`, `RegistrationBillingService`, `WalletService`, 001/002 locking patterns).

All Technical Context entries in plan.md are resolved — no NEEDS CLARIFICATION remains.

## R1. How-doc reconciliation with the clarified spec

- **Decision**: Three corrections to the How-doc sketch, all mandated by the 2026-09-08 clarification session: (1) **drop `expires_at`** from `student_discounts`, drop `isExpired()` and the `expired()` factory state — year/semester scope is the only time boundary; (2) **revocation is not blocked by paid applications** — recorded applications on paid tickets are immutable, but the discount's unused remaining balance stays revocable (supersedes the How-doc `revoke()` guard "يرفض لو فيه usage على حافظة paid"); (3) **CSV import grants fixed-amount discounts scoped to the current year/semester only** — no percentage or term columns in the file format.
- **Rationale**: The How-doc predates the clarification session; the spec's Clarifications section is authoritative (Q2/Q4/Q6 + the four `/speckit.clarify` answers). Keeping `expires_at` would create a second, contradictory time-boundary concept; blocking remainder revocation strands balance with no legal path (spec US2 scenario 8); extended CSV columns invent a format no legacy file matches.
- **Alternatives considered**: Honor the How-doc verbatim — rejected, contradicts accepted clarifications; keep `expires_at` nullable-but-unused — rejected, dead schema is a maintenance liability the legacy system is full of.

## R2. Ticket column strategy — `amount` stays the net

- **Decision**: Add exactly two columns to `student_fee_tickets`: `original_amount decimal(10,2) NULL` (null ⇒ ticket has no discount) and `discount_amount decimal(10,2) DEFAULT 0`. `amount` continues to mean **net payable**. Any consumer needing the gross reads `original_amount ?? amount`. No backfill: existing tickets are discount-free by definition.
- **Rationale**: Every existing query (`outstandingTotal`, `hasOutstandingFees`, `checkFeeGate`, `scopeUnpaid`, DailyPayments) filters/sums on `amount` and `status` — keeping `amount` as net means zero behavioral change for discount-free flows (spec SC-003, How-doc §3.3, risk R3). The BR-13 requirement (zero-net = fully paid) falls out for free: `scopeUnpaid` selects `status='pending'`, so a confirmed zero-net ticket (`status='paid'`, `amount=0`) is automatically excluded from all outstanding math.
- **Alternatives considered**: Store net in a new column and keep `amount` as gross — rejected, would silently break every existing consumer; store the breakdown only in `fee_details` JSON — rejected, JSON is not summable/indexable and BR-6 demands derivable-from-ticket figures.

## R3. Double-spend prevention (BR-4 / FR-010 / SC-002)

- **Decision**: Three layers: (1) every application path (`applyToTicket`, `applyToPendingTicket`) runs inside `DB::transaction` and re-reads each discount with `StudentDiscount::whereKey($id)->lockForUpdate()->first()` before allocating; (2) `unique(student_discount_id, student_fee_ticket_id)` on `student_discount_usages` — a discount can never apply twice to the same ticket even if a transaction retries; (3) post-lock re-check: if locked `remaining_amount` is 0 (fixed) the allocation is skipped. Status/remaining updates use the locked instance, never a stale model.
- **Rationale**: MySQL row locks serialize concurrent issuances for the same discount (spec edge case 7); the unique index is the DB-level backstop the legacy system never had. Mirrors the proven locking approach from 001/002 (exam save-time re-check).
- **Alternatives considered**: Optimistic version column with retry — rejected: cashier-visible issuance should not silently retry with different numbers; app-level check without lock — rejected, exactly the legacy flaw. **Test caveat**: sqlite (tests) treats `lockForUpdate` as a no-op, so the concurrency test simulates read→lock→write interleaving sequentially and asserts the unique index + remaining-balance guard; the true MySQL guarantee is documented for deployment.

## R4. Application timing — inside the issuance transaction

- **Decision**: Discounts are applied at **issuance**, never at payment. `FeeIssuance::generateTickets()` already wraps ticket creation in one `DB::transaction` (line ~252); after each `StudentFeeTicket::create`, `DiscountService::applyToTicket()` runs in the **same transaction**, writing ticket snapshot columns + usage rows + discount status/remaining in one atomic unit. Payment (`FeePayment`) only reads the stored three numbers.
- **Rationale**: The legacy add-then-subtract arithmetic at `payTicket` time (discount "counted as paid" then removed from the ledger) is the root cause of its reconciliation bugs; the spec (FR-005, BR-6 lineage) requires the ticket to carry its final numbers from birth. Same-transaction ⇒ a ticket can never exist without its discount (or vice versa).
- **Alternatives considered**: Apply at payment confirmation — rejected (legacy disease); scheduled/queued application — rejected, constitution forbids queues and introduces a visible inconsistency window.

## R5. Allocation algorithm — oldest-first, capped, half-up

- **Decision**: `planApplication(Collection $discounts, string $originalAmount): array{applied: array<int,string>, net: string}` is a **pure function** (no DB writes) over discounts pre-sorted by `created_at ASC, id ASC`. For each discount: fixed → `min(remaining_amount, unapplied_original)`; percentage → `round(value/100 × original_amount, 2, PHP_ROUND_HALF_UP)` capped at unapplied original, consumed from no balance. Arithmetic uses decimal strings / integer centi-EGP internally; the result is frozen into the ticket (FR-009). Zero-value fee + percentage ⇒ no allocation; because `planApplication` is pure, the **caller** (`applyToTicket`/issuance flow) emits the system-log notice via `Log::info` (FR-011) — no user-facing record in v1.
- **Rationale**: Purity makes the money math exhaustively unit-testable (constitution V) without transactions; oldest-first is spec FR-008; half-up rounding is spec FR-019; percentages having no balance pool is clarification Q2.
- **Alternatives considered**: Float math — forbidden; newest-first or admin-chosen order — rejected, spec pins oldest-first for determinism.

## R6. Zero-net ticket payment path (Q3 / BR-8 / BR-13)

- **Decision**: In `FeePayment::confirmPayment()`: (1) when **all** selected registration tickets have `amount == 0`, skip the `ministerial_receipt_end` range check and consume no receipt number; the confirmation is still a **manual cashier action** (no auto-closure) and stamps `status=paid`, `paid_at`, and a note "سداد بخصم كامل"; (2) guard the existing wallet deposit (`FeePayment.php` ~line 217) with `if ($ticket->amount > 0)` so no `WalletTransaction` of value 0 is ever created; (3) mixed selections (zero-net + positive tickets) still require the receipt number for the positive ones only.
- **Rationale**: Clarification Q3 (decided): manual confirmation without ministerial number; constitution I forbids zero-value ledger rows; BR-13 holds automatically per R2 once status flips to paid. A regression test asserts `checkFeeGate` passes and `outstandingTotal` excludes the confirmed zero ticket.
- **Alternatives considered**: Auto-close zero tickets at issuance — explicitly rejected by Q3 ("لا إقفال تلقائي") — the human confirmation keeps an audit trace at the counter.

## R7. Pending-ticket re-pricing (BR-7 / FR-015)

- **Decision**: Granting a discount never touches existing pending tickets. `Discounts\Index` shows an "تطبيق على الحافظة القائمة" action when the discount's scope matches a pending ticket; it calls `DiscountService::applyToPendingTicket()`, which re-runs `planApplication` against that ticket's current net, re-prices `original/discount/amount`, writes usage rows, and records an `edited` discount event. Paid tickets are excluded from selection entirely.
- **Rationale**: The legacy behavior (delete pending tickets, refund wallet, zero `paid_payments`) is the destructive pattern this feature exists to kill; explicit, logged, reversible-by-hand re-pricing satisfies FR-015 without silent mutation.
- **Alternatives considered**: Auto-re-price pending tickets on grant — rejected (FR-015 "MUST NOT silently modify"); cancel-and-re-issue only — kept as the manual alternative, but the one-click action is required by FR-015.

## R8. Audit trail design (FR-020 / SC-001)

- **Decision**: Three complementary stores, no new packages: (1) columns on `student_discounts` — `created_by/created_at`, `revoked_by/revoked_at/revoked_reason`; (2) `student_discount_usages` rows — who applied what to which ticket when; (3) new `student_discount_events` table (`student_discount_id`, `action` enum granted|edited|revoked|auto_revoked, `meta` JSON old/new values, `user_id`, timestamps) — the only place **edit** history can survive, since edits overwrite the row. The screen's "سجل التطبيقات" tab merges events + usages into one timeline.
- **Rationale**: The project has no activity-log package and the constitution bans adding dependencies casually; FR-020 explicitly lists "edit" as an audited event, which the How-doc's column-only scheme cannot record. The events table is 4 columns and append-only — minimal and queryable.
- **Alternatives considered**: Install spatie/laravel-activitylog — rejected (no new packages, and domain-specific old/new money snapshots belong in a domain table); JSON history blob on the discount row — rejected, unqueryable for reports.

## R9. Editability rules (deferred spec Q1 → planning decision)

- **Decision**: A discount is editable **only while `status = active` and it has zero usage rows**; editable fields: value, scope, fee, year, semester, reason, decision number. Any edit writes an `edited` event with old/new values. Partially applied / exhausted / revoked discounts are immutable — corrections go through revoke (+ re-grant). Enforced in `DiscountService::update()` with a typed exception, mirrored in the UI (edit button hidden unless eligible).
- **Rationale**: The spec's clarify session left Q1 unanswered; the plan must still be testable. Restricting edits to untouched active rows is the smallest surface that satisfies FR-020/FR-021 without the legacy hazard of mutating a discount that already moved money. Recorded here so `/speckit.tasks` and acceptance tests pin one behavior.
- **Alternatives considered**: Edit anytime with re-pricing cascade — rejected, drags paid tickets into scope (FR-016 conflict); no editing at all — rejected, typos in reason/decision number are the common case and revoke-regrant for a typo pollutes the audit chain.

## R10. Permissions, routing, and sidebar (FR-021 / BR-12)

- **Decision**: New `discounts` module in `config/permissions.php` — `discounts.view` (عرض), `discounts.create` (إنشاء), `discounts.edit` (تعديل), `discounts.revoke` (إلغاء) with Arabic labels, seeded idempotently by `PermissionsSeeder`. Route `finance/discounts` → `Livewire\Admin\Finance\Discounts\Index` with `permission:discounts.view` middleware; every write action additionally `abort_unless(auth()->user()->can('discounts.<action>'), 403)`; sidebar link inside the finance group behind `@can`. Applying discounts at issuance requires no new permission — it inherits the existing `finance` rights that already gate `FeeIssuance`/`FeePayment`.
- **Rationale**: Constitution III's layered enforcement; spec FR-021's four actions; issuance-time application is system behavior triggered by an already-gated action, so a separate "apply" permission would gate nothing real.
- **Alternatives considered**: Reuse `finance.edit` for everything — rejected, spec demands distinct view/create/edit/revoke; Policies — forbidden by constitution.

## R11. CSV bulk import (FR-024)

- **Decision**: `WithFileUploads` on `Discounts\Index`; native PHP parsing (BOM strip, `str_getcsv`), header tolerance by normalizing (trim, collapse spaces, accept the legacy spelling variants of كود الطالب/نوع الرسوم/قيمة الخصم/سبب الخصم). Every row validated before any write (student exists & not soft-deleted, fee type maps to a known scope, amount > 0, reason present); any failure ⇒ whole file rejected with a per-row error table, nothing persisted. Success path runs one `DB::transaction` creating fixed-amount discounts scoped to the **current** year/semester, each with a `granted` event (meta: source=csv).
- **Rationale**: Clarification (CSV scope) pins fixed-amount/current-term; all-or-nothing is spec FR-024 + edge case 12; validating-then-writing avoids partial imports without needing file-level rollback complexity.
- **Alternatives considered**: Row-level "skip bad rows" mode — rejected by spec; maatwebsite/excel package — rejected (no new packages; 4 columns don't warrant it).

## R12. Auto-revocation on student deletion/transfer (FR-018)

- **Decision**: Two hooks: (1) `Student::booted()` `deleted` event (soft delete) → revoke all active/partially-applied discounts with reason "حذف الطالب", system actor (`user_id = null` on the event); (2) `StudentTransferService::approve()` → same treatment with reason "تحويل الطالب" executed inside the existing transfer transaction. Exhausted/revoked rows are untouched (history preserved). **v1 surfacing is the discounts-screen audit timeline (T045), not an external notification** — the repo has no `app/Notifications`/`Notification::send` infrastructure and the constitution forbids casually adding dependencies (analyze finding C1).
- **Rationale**: Spec edge case 10 ("لا يتيم مال"); transfer approval already runs a `DB::transaction` with ticket/registration handling — discounts join the same sweep. System-actor events keep the audit honest (no human is blamed).
- **Alternatives considered**: Leave discounts dangling — rejected by FR-018; scheduled cleanup command — rejected (constitution: no queues/schedulers for money invariants; must be synchronous).

## R13. Legacy migration command (FR-025)

- **Decision**: `php artisan discounts:import-legacy` (manual, sandbox-first): reads legacy `students_discounts` rows from the old DB connection, maps `'دراسية' → scope=registration`, `'اخرى' → scope=additional`, `'ادارية' → fixed on the administrative fee`, **skips** `'محفظة'` (wallet gift — report only, per Q5/BR-10) and `'خدمات تعليمية'` (dead code — skipped with report). Only unconsumed rows (legacy heuristic: no used-ticket marker) become `active` discounts marked `granted` with meta `source=legacy-migration`; prints a summary table. Not a seeder, not automatic.
- **Rationale**: Spec US5/P5 — go-live nicety, explicitly optional; the type mapping is exactly the How-doc §11 table; wallet separation is a decided invariant.
- **Alternatives considered**: Full data migration in the deploy pipeline — rejected (Out of Scope); converting wallet-type rows to discounts "for convenience" — rejected by Q5.
