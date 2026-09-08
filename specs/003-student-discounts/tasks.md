# Tasks: Student Discounts

**Input**: Design documents from `/specs/003-student-discounts/`

**Prerequisites**: plan.md (required), spec.md (required for user stories), research.md (R1–R13), data-model.md, contracts/, quickstart.md

**Tests**: INCLUDED — constitution Principle V makes Pest 4 feature tests the definition of done for anything touching billing/permissions. Per quickstart.md's test map, each story's tests are written FIRST and must fail before implementation.

**Organization**: Tasks grouped by user story (US1–US5 from spec.md priorities P1–P5). Money logic (`DiscountService`) is the foundational blocking layer — no UI before it (How-doc build order §16, constitution II/V).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: US1…US5 (spec.md user stories)
- All paths are repository-relative; Laravel 12 single-monolith structure per plan.md

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Schema, enums, models, factory, permission definitions — everything shared by all stories

- [X] 01Create migration `database/migrations/2026_09_08_XXXXXX_create_student_discounts_table.php` via `php artisan make:migration create_student_discounts_table --no-interaction` per data-model.md §1: student_id cascade, scope/fee_id/year_id(nullOnDelete)/semester(Semester enum string)/mode/value/remaining_amount/status default active/reason/decision_number/created_by/revoked_by/revoked_at/revoked_reason, `index(student_id, status, scope)` — **NO expires_at column** (research R1); complete `down()`
- [X] 02[P] Create migration `database/migrations/2026_09_08_XXXXXX_create_student_discount_usages_table.php` per data-model.md §2: discount+ticket FKs cascadeOnDelete, applied_amount decimal(10,2), applied_by nullOnDelete, `unique(student_discount_id, student_fee_ticket_id)` (R3 backstop); complete `down()`
- [X] 03[P] Create migration `database/migrations/2026_09_08_XXXXXX_create_student_discount_events_table.php` per data-model.md §3: discount FK cascade, action string, meta json nullable, user_id nullOnDelete (null = system actor, R12); complete `down()`
- [X] 04[P] Create migration `database/migrations/2026_09_08_XXXXXX_add_discount_columns_to_student_fee_tickets_table.php`: `original_amount decimal(10,2) nullable after amount` + `discount_amount decimal(10,2) default 0 after original_amount` (R2 — `amount` stays net, no backfill); explicit `dropColumn` in `down()`
- [X] 05[P] Create enum `app/Enums/DiscountScope.php`: backed string `Registration='registration'`, `Additional='additional'`, `Any='any'` with Arabic `label()` (رسوم التسجيل / رسوم إضافية محددة / أي رسوم)
- [X] 06[P] Create enum `app/Enums/DiscountMode.php`: `Fixed='fixed'`, `Percentage='percentage'` with Arabic `label()` (مبلغ ثابت (ج.م) / نسبة مئوية (٪))
- [X] 07[P] Create enum `app/Enums/DiscountStatus.php`: `Active`, `PartiallyApplied`, `Exhausted`, `Revoked` with Arabic `label()` + `badgeClass()` per data-model.md state machine
- [X] 08[P] Create enum `app/Enums/DiscountEventAction.php`: `Granted`, `Edited`, `Revoked`, `AutoRevoked` with Arabic `label()`
- [X] 09Create model `app/Models/StudentDiscount.php`: `casts()` method (mode/status/scope/semester enums, value/remaining_amount `decimal:2`, revoked_at datetime), relations `student()/creator()/revoker()/year()/usages()/events()`, helper `appliesTo(string $feeType, ?int $feeId, ?int $yearId, ?Semester $semester): bool` per data-model.md; NO delete path exposed (FR-003)
- [X] 10[P] Create model `app/Models/StudentDiscountUsage.php`: `discount()/ticket()/appliedBy()` relations, `applied_amount => 'decimal:2'` cast — append-only ledger
- [X] 11[P] Create model `app/Models/StudentDiscountEvent.php`: `discount()/user()` relations, `action => DiscountEventAction` cast, `meta => 'array'` cast
- [X] 12Extend `app/Models/StudentFeeTicket.php`: add `original_amount`/`discount_amount` `decimal:2` casts, `discountUsages(): HasMany`, `grossAmount(): float` helper (`original_amount ?? amount`), `hasDiscount(): bool`
- [X] 13[P] Extend `app/Models/Student.php`: add `discounts(): HasMany` (return type hint)
- [X] 14Create `database/factories/StudentDiscountFactory.php` with states `fixed(float $amount)`, `percentage(float $pct)`, `scoped(DiscountScope $scope, ?int $feeId = null)`, `revoked()` — definition defaults: current year/semester null (open), reason Arabic faker, created_by factory user
- [X] 15Add `'discounts'` module to `config/permissions.php` (after `'finance'` at ~line 72): label 'خصومات الطلاب', actions view/create/edit/revoke (عرض/إنشاء/تعديل/إلغاء) per contracts/routes.md
- [X] 16Verify foundation: `php artisan migrate --force && php artisan db:seed --class=PermissionsSeeder --force` succeed; `discounts.*` permissions exist; rollback `php artisan migrate:rollback --step=4` then re-migrate cleanly

**Checkpoint**: Schema + models ready — DiscountService can be built and tested at the data level.

---

## Phase 2: Foundational (Money Core — BLOCKS ALL STORIES)

**Purpose**: `DiscountService` core (grant/eligibility/allocation/application) with tests-first per constitution V. No UI in this phase (How-doc §16: "منطق المال أولًا قبل أي UI").

**⚠️ CRITICAL**: No user story work begins until this phase is complete and green.

- [X] 17Create typed exceptions `app/Exceptions/DiscountRevokeException.php` and `app/Exceptions/DiscountEditException.php` (Arabic messages, constitution I — never generic strings)
- [X] 18Write FAILING core service tests in `tests/Feature/Discounts/DiscountServiceTest.php` (per quickstart test map; REUSE `tests/Pest.php` fixture builders — `billingWorld()`, `issueTicket()` — per constitution V): `eligibleFor` scope/year/semester matching + revoked excluded + no-expiry behavior; `planApplication` full consumption, **partial drawdown (Q1)**, two discounts oldest-first capped at original (FR-007/008), percentage half-up rounding incl. 33.33% edge (FR-019, R5), percentage on zero-value fee → no allocation + system-log notice asserted (FR-011, caller-side `Log`); `applyToTicket` ticket stores original/discount/net + usage rows + remaining/status transitions per data-model state machine + invariants 1–4; `grant` writes `granted` event, initializes `remaining_amount = value` (fixed), and is immediately active (FR-026). Run `php artisan test --compact --filter=DiscountServiceTest` and confirm RED
- [X] 19Create `app/Services/DiscountService.php` with constructor promotion; implement `grant(array $data, User $actor): StudentDiscount` inside `DB::transaction` + `granted` event (meta may carry `source`); fixed mode initializes `remaining_amount = value`
- [X] 20Implement `DiscountService::eligibleFor(Student, DiscountScope|string, ?int, ?int, ?Semester): Collection` — indexed query on (student_id, status, scope), active-only, in-scope filter via `appliesTo()` (R4)
- [X] 21Implement in `app/Services/DiscountService.php`: `DiscountService::planApplication(Collection, string): array` — PURE function (no DB writes), pre-sorted `created_at ASC, id ASC`, centi-EGP integer math, fixed → min(remaining, unapplied), percentage → half-up capped, returns `['applied' => [id => amount], 'net' => string]` (R5)
- [X] 22Implement in `app/Services/DiscountService.php`: `DiscountService::applyToTicket(StudentFeeTicket, array, User): void` — `DB::transaction` + `StudentDiscount::whereKey()->lockForUpdate()->first()` per discount, post-lock remaining re-check, writes ticket snapshot columns + usage rows + status/remaining transitions (R3/R4); percentage consumes no balance (Q2)
- [X] 23Run `php artisan test --compact --filter=DiscountServiceTest` → GREEN; `vendor/bin/pint --dirty --format agent` clean

**Checkpoint**: Money core proven at service level — stories can now build UI/integrations on it.

---

## Phase 3: User Story 1 - Grant + Automatic Application + Payment Transparency (Priority: P1) 🎯 MVP

**Goal**: Admin grants a documented discount; issuance auto-applies it (original/discount/net stored); cashier collects net; receipt shows the discount; zero-net tickets confirm manually without ministerial numbers and count as fully paid.

**Independent Test**: spec.md US1 scenarios 1–5 — grant 500 on registration fees → issue 1,200 ticket → stored 1,200/500/700, discount exhausted; 50% on 300 fee → net 150 frozen; 1,000-on-1,000 → zero-net manual confirm, no receipt number, `checkFeeGate` passes afterward; 403 without permission.

### Tests for User Story 1 ⚠️ WRITE FIRST, MUST FAIL

- [X] 24[P] [US1] Create `tests/Feature/Discounts/FeeIssuanceTest.php`: issuance WITH discount ⇒ `assertDatabaseHas` original/discount/net + discount status update; issuance WITHOUT discount ⇒ `original_amount` null, `discount_amount` 0 (no regression, SC-003); preview badge data present (Livewire `assertSee`)
- [X] 25[P] [US1] Create `tests/Feature/Discounts/FeePaymentTest.php`: three-line display; **zero-net path (Q3/R6)** — confirm with all-zero selection succeeds WITHOUT consuming a ministerial receipt number, sets paid + note 'سداد بخصم كامل', creates NO 0-value `WalletTransaction`; **BR-13 regression** — after zero-net paid, `RegistrationBillingService::checkFeeGate` passes and `outstandingTotal` excludes it; mixed zero+positive selection still consumes numbers for positives only
- [X] 26[P] [US1] Create `tests/Feature/Discounts/DiscountsScreenTest.php`: grant via component persists discount + `granted` event + immediately active (no approval, FR-026); validation failures (value ≤ 0, percentage > 100, additional-scope missing fee_id, missing reason) with Arabic messages; 403 for **view/create only** via `Livewire::actingAs()` (edit/revoke actions don't exist until US2 — their 403 assertions live in T034)

### Implementation for User Story 1

- [X] 27[US1] Create `app/Livewire/Admin/Finance/Discounts/Index.php`: full-page component extending admin layout; `$search/$statusFilter/$scopeFilter/$yearFilter/$semesterFilter`; paginated `discounts()` with `loadMissing`; create-modal state + form fields; `rules()`/`messages()` per contracts/discounts-ui.md; `mount()` + `save()` guarded by `abort_unless(auth('web')->user()->can('discounts.create'), 403)`; delegates to `DiscountService::grant()`; toast via event bridge (depends on T019)
- [X] 28[US1] Create `resources/views/livewire/admin/finance/discounts/index.blade.php`: Sneat/Bootstrap RTL markup — filters bar, discounts table (student, scope label, mode+value, applied/remaining, `DiscountStatus` badge, reason, decision number), create modal (mode toggle مبلغ/نسبة, scope + conditional fee select, year/semester optional, reason, decision_number), `@can` wrappers, `wire:model.live` search
- [X] 29[US1] Register route `Route::get('finance/discounts', Index::class)->name('admin.finance.discounts')->middleware('permission:discounts.view')` in `routes/web.php` (finance group) + sidebar link "خصومات الطلاب" in the finance group Blade layout behind `@can('discounts.view')`
- [X] 30[US1] Integrate `app/Livewire/Admin/Finance/FeeIssuance.php`: after `loadFees()` call `DiscountService::eligibleFor()` per fee line → "خصم متاح" badge + expected net (reactive) + pre-generation summary (إجمالي أصلي/خصومات/صافي); inside the EXISTING `generateTickets()` `DB::transaction` (~line 252), after each `StudentFeeTicket::create` (~line 362) call `planApplication()` + `applyToTicket()` — same transaction (R4)
- [X] 31[US1] Integrate `app/Livewire/Admin/Finance/FeePayment.php`: display original/discount/net per ticket (views + component); zero-net selection skips `ministerial_receipt_end` check and consumes no number; manual confirmation stamps paid + 'سداد بخصم كامل' note (no auto-closure); wrap existing wallet deposit (~line 217) in `if ($ticket->amount > 0)` (R6)
- [X] 32[US1] Edit print receipt `resources/views/.../print-tickets.blade.php` (locate exact path via existing usage): add line "خصم: {discount_amount} ج.م — السبب: {reason(s)}" when `discount_amount > 0` (FR-012, SC-004)
- [X] 33[US1] Run `php artisan test --compact --testsuite=Feature --filter="Discount|FeeIssuance|FeePayment"` → GREEN (T024–T026 pass); `vendor/bin/pint --dirty --format agent` clean

**Checkpoint**: MVP — discounts grant → auto-apply → transparent payment works end-to-end. Independent of US2–US5.

---

## Phase 4: User Story 2 - Lifecycle Control: Drawdown, Revoke, Re-price, Safety (Priority: P2)

**Goal**: Governed lifecycle: partial drawdown across tickets (already core), oldest-first (core), explicit pending-ticket re-pricing, revocation rules (remainder revocable, paid applications immutable), edit gating, auto-revoke on deletion/transfer, concurrency guarantee.

**Independent Test**: spec.md US2 scenarios 1–8 — 300 discount vs two 200 tickets; two discounts on one 500 ticket; pending predates discount → explicit re-price to 700; revoke remainder only; paid-application immutability; interleaved concurrency; soft-delete/transfer auto-revoke.

### Tests for User Story 2 ⚠️ WRITE FIRST, MUST FAIL

- [X] 34[P] [US2] Create `tests/Feature/Discounts/DiscountLifecycleTest.php` (reuse `tests/Pest.php` fixture builders): **concurrency (R3)** — simulated read-lock-write interleaving on one 300 discount ⇒ exactly one application, unique-index violation on repeat; `revoke` — remainder-only success, full-consumed-by-paid rejection via `DiscountRevokeException` with Arabic message, reason required; `update` — allowed only active+zero-usages (R9), typed `DiscountEditException` otherwise, `edited` event carries old/new; `applyToPendingTicket` — pending re-priced + logged, paid target refused; auto-revoke on `Student::delete()` (soft) and `StudentTransferService::approve()` with `auto_revoked` events, `user_id` null, exhausted/revoked untouched, and revocations visible in the screen's audit history; **edit/revoke 403 gates** for users lacking `discounts.edit`/`discounts.revoke`. Confirm RED

### Implementation for User Story 2

- [X] 35[US2] Implement in `app/Services/DiscountService.php`: `DiscountService::revoke(StudentDiscount, string $reason, ?User): void` — state guard (active/partially_applied only), cancels remaining balance only, paid applications immutable (R1), writes `revoked`/`auto_revoked` event
- [X] 36[US2] Implement in `app/Services/DiscountService.php`: `DiscountService::update(StudentDiscount, array, User): StudentDiscount` — R9 gate (active && zero usages), `edited` event with old/new meta
- [X] 37[US2] Implement in `app/Services/DiscountService.php`: `DiscountService::applyToPendingTicket(StudentDiscount, StudentFeeTicket, User): void` — pending+scope validation, re-runs `planApplication` against current net, re-prices columns + usage + `edited` event (R7)
- [X] 38[US2] Extend `app/Livewire/Admin/Finance/Discounts/Index.php` + `index.blade.php`: revoke modal (mandatory `revoked_reason`, `window.confirmAction()`), edit modal rendered only when R9-eligible, "تطبيق على الحافظة القائمة" button shown only when eligible pending ticket exists; each action `abort_unless` on `discounts.revoke`/`discounts.edit`
- [X] 39[US2] Wire auto-revocation (R12): `deleted` hook in `app/Models/Student.php` `booted()` → revoke sweep; add sweep inside existing `DB::transaction` in `StudentTransferService::approve()` (`app/Services/StudentTransferService.php` ~line 182) with reason 'تحويل الطالب'; both write `auto_revoked` events with `user_id = null` — surfaced in the discounts screen audit history (FR-018; **no external notification channel in v1**)
- [X] 40[US2] Run `php artisan test --compact --filter="DiscountLifecycle|DiscountService"` → GREEN; `vendor/bin/pint --dirty --format agent` clean

**Checkpoint**: US1 + US2 both independently functional; the destructive legacy patterns (delete tickets, double-spend, silent mutation) are now impossible.

---

## Phase 5: User Story 3 - Audit & Reporting (Priority: P3)

**Goal**: Discounts report (period/department/year/student filters, ticket-derived figures), student financial statement four figures, daily payments discount line, unified application/audit timeline.

**Independent Test**: spec.md US3 scenarios — report rows match stored ticket lines exactly (0 discrepancy, SC-005); statement reconciles; every mutation visible in audit history.

### Tests for User Story 3 ⚠️ WRITE FIRST, MUST FAIL

- [X] 41[P] [US3] Create `tests/Feature/Discounts/DiscountReportingTest.php`: report per-row student/type/applied/reason/decision/granter/date + totals equal `Σ usages.applied_amount` and ticket `discount_amount` (never recomputed); `StudentFinancialStatus` shows original/total-discounts/paid/remaining consistent with tickets (incl. legacy `original_amount=null` → gross = amount); `DailyPayments` surfaces discount; audit timeline merges events + usages chronologically. Confirm RED

### Implementation for User Story 3

- [X] 42[US3] Add report section to `app/Livewire/Admin/Finance/Discounts/Index.php` + Blade tab: filters (period/department/level/student), paginated rows derived from `student_discount_usages` ⋈ `student_fee_tickets` snapshots (FR-022, BR-6)
- [X] 43[US3] Extend `app/Livewire/Admin/Finance/StudentFinancialStatus.php` + its Blade: four figures from ticket columns (FR-023)
- [X] 44[P] [US3] Extend `app/Livewire/Admin/Finance/DailyPayments.php` + its Blade: discount column from `ticket.discount_amount` (FR-023)
- [X] 45[US3] Add "سجل التطبيقات" timeline to `app/Livewire/Admin/Finance/Discounts/Index.php`: merged `events()` + `usages()` ordered by timestamp (FR-020)
- [X] 46[US3] Run `php artisan test --compact --filter=DiscountReporting` → GREEN; `vendor/bin/pint --dirty --format agent` clean

**Checkpoint**: Governance complete — SC-001/SC-005 verifiable from the UI.

---

## Phase 6: User Story 4 - Bulk CSV Import (Priority: P4)

**Goal**: All-or-nothing CSV import of fixed-amount, current-term discounts with legacy header tolerance and per-row error reporting.

**Independent Test**: spec.md US4 — valid 5-row file ⇒ 5 active discounts; one bad row ⇒ zero created, error names row+column.

### Tests for User Story 4 ⚠️ WRITE FIRST, MUST FAIL

- [X] 7[P] [US4] Create `tests/Feature/Discounts/DiscountCsvImportTest.php`: valid import creates fixed discounts scoped to current year/semester with `granted` events (meta source=csv); header variants tolerated (قيمه/قيمة, BOM, spaces); unknown student code / negative amount / missing reason ⇒ whole file rejected with per-row errors, `assertDatabaseCount(student_discounts, 0)`; permission gate. Confirm RED

### Implementation for User Story 4

- [X] 8[US4] Implement CSV import in `app/Livewire/Admin/Finance/Discounts/Index.php` (+ upload UI in Blade): `WithFileUploads`, native `str_getcsv` parse, header normalization, full pre-validation then single `DB::transaction` of `DiscountService::grant()` calls (R11, FR-024) — fixed amounts, current term only (clarification)

**Checkpoint**: Volume granting available; P1 screen still covers every case.

---

## Phase 7: User Story 5 - Legacy Discount Carry-Over (Priority: P5)

**Goal**: Optional manual console import of unconsumed legacy `students_discounts` rows; wallet-gift and dead-code types skipped with report.

**Independent Test**: spec.md US5 — seeded legacy rows: only non-wallet unconsumed become active discounts marked migrated.

### Tests for User Story 5 ⚠️ WRITE FIRST, MUST FAIL

- [X] 49[P] [US5] Create `tests/Feature/Discounts/LegacyDiscountImportTest.php`: type mapping دراسية→registration, اخرى→additional, ادارية→fixed-on-admin-fee; 'محفظة' and 'خدمات تعليمية' skipped with summary counts; consumed rows excluded; `granted` events carry meta source=legacy-migration. Confirm RED

### Implementation for User Story 5

- [X] 50[US5] Create `app/Console/Commands/ImportLegacyDiscounts.php` (`php artisan discounts:import-legacy`, R13): reads legacy connection, maps types, never converts wallet-gift rows (Q5/BR-10), prints per-type summary; manual/sandbox-only — not scheduled, not a seeder

**Checkpoint**: Cutover path exists; feature fully functional without it.

---

## Phase 8: Polish & Cross-Cutting

- [X] 1[P] Extend `database/seeders/DemoDataSeeder.php`: demo student with active discount + applied ticket for manual browser demo
- [X] 2[P] Full regression: `php artisan test --compact` (entire suite — legacy finance tests MUST stay green, R2/R3 risk) + `vendor/bin/pint --dirty --format agent` clean
- [ ] T053 Run `quickstart.md` manual walkthrough steps 1–7 in browser (grant → issue → pay → zero-net → pending re-price → revoke-paid → report); verify Arabic RTL copy, toasts, and receipt line end-to-end

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (P1 phase)**: none — start immediately
- **Foundational (P2 phase)**: after Setup — **BLOCKS every user story** (money core + tests green first)
- **US1 (P3 phase)**: after Foundational — the MVP
- **US2 (P4 phase)**: after Foundational; integrates with US1's screen (T038 extends T027/T028) — deliver after US1
- **US3 (P5 phase)**: after US1+US2 produce usage/event data (report is read-only otherwise)
- **US4 (P6 phase)**: after US1 (extends same component)
- **US5 (P7 phase)**: after Foundational only — independent of UI stories
- **Polish**: after all delivered stories

### Within Each User Story

- Tests written FIRST and confirmed RED (constitution V)
- Service methods before component wiring; component before Blade/route where same file order matters
- Story green + pint before next story

### Critical Path

T001→T009→T018→T019→T021→T022→T023→(US1: T027→T030→T031→T033)→demo-able MVP.

---

## Parallel Execution Examples

```bash
# Phase 1 — migrations (T002/T003/T004) and enums (T005–T008) all parallel;
# models T010/T011 parallel with T009's completion.

# Phase 3 (US1) — write all three test files together first:
Task: T024 tests/Feature/Discounts/FeeIssuanceTest.php
Task: T025 tests/Feature/Discounts/FeePaymentTest.php
Task: T026 tests/Feature/Discounts/DiscountsScreenTest.php

# Phase 5 (US3) — report surfaces are independent files:
Task: T043 StudentFinancialStatus
Task: T044 DailyPayments

# After Foundational (T023), US5 (legacy command) can run in parallel with US1 by a second developer.
```

---

## Implementation Strategy

### MVP First (User Story 1 only)

1. Phase 1 Setup → 2. Phase 2 Foundational (money core GREEN) → 3. Phase 3 US1 → 4. **STOP & VALIDATE**: quickstart steps 1–4 → 5. Safe to deploy: `discounts.*` ungranted ⇒ zero behavior change (contracts/routes.md safe-launch).

### Incremental Delivery

1. +US2 → lifecycle safety complete (revoke/re-price/concurrency/auto-revoke)
2. +US3 → governance/audit adoption-ready
3. +US4 → term-start bulk operations
4. +US5 → legacy cutover (optional, sandbox first)

### Parallel Team Strategy

After T023: Dev A → US1 (screen+integrations), Dev B → US5 (command, zero file overlap), Dev C → US2 service methods (T035–T037, different methods, same file — coordinate or sequence within DiscountService.php).

---

## Notes

- [P] = different files, no incomplete dependency; T027/T038 and T035–T037 touch the same files — never parallelize those.
- Every money mutation: `DB::transaction` + `lockForUpdate` + decimal strings (constitution I); no floats anywhere.
- All test tasks reuse the global fixture builders in `tests/Pest.php` (`billingWorld()`, `issueTicket()`, `fundWallet()`) — do not re-handcraft worlds (constitution V).
- No new packages, no Form Requests for Livewire, no roles/policies, no Tailwind restyling (constitution II/III/IV + Technical Constraints).
- sqlite concurrency caveat documented in research R3 — the test simulates interleaving; MySQL row locks are the production guarantee.
- Commit after each checkpoint: `feat(discounts): …` per repo conventional style.
- Stop at any checkpoint to validate the story independently before continuing.

---

## Phase 9: Convergence (remaining work found by `/speckit.converge`, 2026-09-08)

**Purpose**: Close gaps between spec/plan/contracts intent and the current code. Ordered CRITICAL first. Constitution I (money integrity) drives F1.

- [X] 54[CRITICAL] Add `revertTicket(StudentFeeTicket $ticket, ?User $actor): void` to `app/Services/DiscountService.php` — inside `DB::transaction`: for each usage row add `applied_amount` back to the discount's `remaining_amount` and move exhausted/partially_applied fixed discounts back to active (percentage: delete usage, no balance), then delete the usage rows and log an `edited` event; call it in `deleteTicket()` of `app/Livewire/Admin/Finance/FeeIssuance.php` BEFORE `$ticket->delete()` so hard-deleting a discounted pending ticket can never silently destroy balance; add restoration + invariant-2/3 tests in `tests/Feature/Discounts/DiscountLifecycleTest.php` per data-model invariants 2/3 + Constitution I (contradicts)
- [X] 55Catch `DiscountRevokeException` / `DiscountEditException` in `save()`, `updateDiscount()`, `revoke()`, and `applyToPendingTicket()` of `app/Livewire/Admin/Finance/Discounts/Index.php`, surfacing the Arabic exception message as an error toast (mirror the catch pattern at `app/Livewire/Admin/ExamSchedule/Index.php:88`); add stale-page race tests (revoke/exhausted, edit/now-applied) asserting a toast instead of a 500 in `tests/Feature/Discounts/DiscountLifecycleTest.php` per Constitution IV/V (partial)
- [X] 56Guard `DiscountService::applyToPendingTicket()` in `app/Services/DiscountService.php` to throw `DiscountEditException` (Arabic) when the chosen discount's scope does not match the ticket or its balance is already applied there — never toast success for a no-op; add the rejection test in `tests/Feature/Discounts/DiscountLifecycleTest.php` per contracts/finance-integration.md R7 (partial)
- [X] 57Enforce integrity invariants inside `app/Services/DiscountService.php` `grant()`/`update()` — value > 0, percentage mode requires 0 < value ≤ 100, non-empty reason (typed exception with Arabic message, mirroring data-model.md's validation table) so direct/migration callers cannot persist an invalid discount; add guard tests in `tests/Feature/Discounts/DiscountServiceTest.php` per data-model validation table + Constitution I (partial)
- [X] 58Add the contract-specified year/semester list filters to `app/Livewire/Admin/Finance/Discounts/Index.php` (`$yearFilter`, `$semesterFilter`, `updated()` resetPage hooks, query constraints) and matching selects in `resources/views/livewire/admin/finance/discounts/index.blade.php`; extend the filter assertion in `tests/Feature/Discounts/DiscountsScreenTest.php` per contracts/discounts-ui.md (partial)
- [X] 59Add missing acceptance coverage in `tests/Feature/Discounts/`: (1) issue an `additional`-fee ticket through `FeeIssuanceTest` with a 50% discount and assert frozen original/discount/net even after the underlying fee definition changes (spec US1 Independent Test + AC2, BR-5); (2) GET `admin.finance.print-tickets` for a discounted ticket via a permissioned user and assert the 'خصم' line with reason renders (SC-004, FR-012) (missing)
- [X] 60Render the 'خصم متاح' badge + expected net on the military-education and other-fees rows in `resources/views/livewire/admin/finance/fee-issuance.blade.php` using the already-computed `discountSummaries` keys `military_education-*` / `other-*`; extend a `FeeIssuanceTest` assertion accordingly per contracts/finance-integration.md (partial)
- [X] 61Add `admin.finance.discounts` to the parent finance group's `isActiveRoute([...])` array in `resources/views/admin/layouts/sidebar.blade.php` (~line 224) so the group opens/highlights on the discounts screen per contracts/routes.md (partial)
