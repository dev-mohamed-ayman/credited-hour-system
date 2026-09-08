# Implementation Plan: Student Discounts

**Branch**: `003-student-discounts` | **Date**: 2026-09-08 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/003-student-discounts/spec.md` + root proposal `Student Discounts — Implementation Plan (How).md` (reconciled against the 2026-09-08 clarification session — see research.md R1)

## Summary

First-class student discounts for the finance module: admins grant documented discounts (fixed amount or percentage, scoped by fee category/year/semester), the system applies eligible ones automatically inside the existing ticket-issuance transaction, cashiers collect the net with a transparent original/discount/net receipt, and every grant/edit/revoke/application is auditable without recomputation. Implementation centers on a new `DiscountService` (money logic, row-locked), two new tables (`student_discounts`, `student_discount_usages`) plus a thin audit table (`student_discount_events`), two nullable/default columns on `student_fee_tickets` (`amount` stays the net so every existing query keeps working), a new Livewire admin screen, and surgical edits to `FeeIssuance`, `FeePayment`, and the three report/print views.

## Technical Context

**Language/Version**: PHP 8.4, Laravel 12

**Primary Dependencies**: Livewire 3, Spatie Permission 8 (direct permissions, no roles), Pest 4, Pint; no new packages (CSV via native PHP, per exam-scheduling precedent)

**Storage**: MySQL (production), sqlite in-memory (tests); money columns `decimal(10,2)`

**Testing**: Pest 4 feature tests (`tests/Feature/Discounts/`) — service-level money tests + `Livewire::actingAs()` integration tests per constitution Principle V

**Target Platform**: Existing admin web portal (staff guard `web`), RTL Bootstrap/Sneat template

**Project Type**: Single Laravel web application (monolith)

**Performance Goals**: Discount resolution adds one indexed query + in-memory allocation per fee line at issuance; issuance UX unchanged (SC-003: zero extra cashier steps)

**Constraints**: No double-spend under concurrency (row lock + DB unique backstop); all mutations inside `DB::transaction`; percentage rounds half-up to 2 decimals; zero-net tickets never consume a ministerial receipt number and never create a 0-value wallet transaction

**Scale/Scope**: Institutional student body (thousands of students, dozens of fee definitions per term); 2 new domain tables + 1 audit table + 2 ticket columns + 1 screen + 3 view edits + 1 optional console command

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Gate | Status |
|-----------|------|--------|
| I. Money Integrity Is Sacred | All discount mutations inside `DB::transaction` with `lockForUpdate` on the discount row; ticket stores final `original/discount/net` so reports never recompute; `decimal(10,2)` everywhere, no floats; wallet untouched except the explicit zero-deposit skip (no `WalletTransaction` with amount 0); gift balances stay on `WalletService::deposit` (FR-004) | ✅ PASS |
| II. Thin Livewire, Real Services | All discount math/lifecycle in `App\Services\DiscountService` returning result arrays / throwing typed exceptions; `Discounts\Index`, `FeeIssuance`, `FeePayment` only orchestrate; in-component `rules()` validation (no Form Requests for Livewire) | ✅ PASS |
| III. Permission-Gated Access — No Roles | New `discounts` module in `config/permissions.php` (view/create/edit/revoke, Arabic labels, idempotent seeder); layered enforcement: route `permission:` middleware + `abort_unless(...->can())` in actions + `@can` in Blade; no policies, no roles | ✅ PASS |
| IV. Arabic-First, Bootstrap-Only UX | Enum `label()` in Arabic; all screen/receipt/validation copy hardcoded Arabic; Sneat/Bootstrap markup only; toast via existing event bridge; statuses via backed enums with badges | ✅ PASS |
| V. Verifiable Change — Test-First | Money-critical coverage mandated before done: partial drawdown, oldest-first cap, percentage rounding, concurrency (single application), zero-net gate pass (`checkFeeGate`/`outstandingTotal` regression), revoke immutability, CSV all-or-nothing; reuse `tests/Pest.php` fixture builders (`billingWorld()`, `issueTicket()`, `fundWallet()`) | ✅ PASS |

**Gate result**: PASS — no violations; Complexity Tracking unused.

**Post-Phase-1 re-check**: PASS. Design artifacts (research.md, data-model.md, contracts/) introduce no new packages, no new base directories, no roles/policies, no Tailwind restyling; the only deviation from the How-doc (`student_discount_events` table, expiry removal, revocation-of-remainder) is driven by spec FR-020 and the 2026-09-08 clarifications (R1, R8).

## Project Structure

### Documentation (this feature)

```text
specs/003-student-discounts/
├── plan.md              # This file
├── research.md          # Phase 0 output — R1–R13
├── data-model.md        # Phase 1 output — tables, enums, state machine, validation
├── quickstart.md        # Phase 1 output — runnable validation guide
├── checklists/
│   └── requirements.md  # Spec quality checklist (from /speckit.specify)
└── contracts/           # Phase 1 output
    ├── routes.md        # Routes + permission matrix
    ├── discounts-ui.md  # Admin discounts screen contract
    └── finance-integration.md # FeeIssuance/FeePayment/reports contract
```

### Source Code (repository root)

```text
app/
├── Enums/
│   ├── DiscountScope.php            # registration | additional | any
│   ├── DiscountMode.php             # fixed | percentage
│   ├── DiscountStatus.php           # active | partially_applied | exhausted | revoked
│   └── DiscountEventAction.php      # granted | edited | revoked | auto_revoked
├── Models/
│   ├── StudentDiscount.php          # new
│   ├── StudentDiscountUsage.php     # new
│   ├── StudentDiscountEvent.php     # new (audit trail)
│   ├── StudentFeeTicket.php         # + discountUsages(), + casts, original/discount helpers
│   └── Student.php                  # + hasMany discounts()
├── Services/
│   └── DiscountService.php          # new — eligibleFor/planApplication/applyToTicket/
│                                    #     applyToPendingTicket/revoke/grant (all money logic)
├── Livewire/Admin/Finance/
│   ├── Discounts/Index.php          # new screen (+ index.blade.php, CSV import, revoke, re-price)
│   ├── FeeIssuance.php              # edit: preview badges + apply inside generateTickets txn
│   ├── FeePayment.php               # edit: original/discount/net lines + zero-net path + deposit skip
│   ├── StudentFinancialStatus.php   # edit: discount column from ticket snapshots
│   └── DailyPayments.php            # edit: discount line surfaced
└── Exceptions/
    ├── DiscountRevokeException.php  # typed domain exception (constitution I)
    └── DiscountEditException.php    # R9 edit-gate violation

database/
├── migrations/
│   # create_student_discounts_table
│   # create_student_discount_usages_table
│   # create_student_discount_events_table
│   # add_discount_columns_to_student_fee_tickets_table   (all additive, complete down())
└── factories/
    └── StudentDiscountFactory.php   # states: fixed(), percentage(), scoped(), revoked()

resources/views/livewire/admin/finance/discounts/          # new Blade (Sneat markup)
resources/views/... print-tickets.blade.php                # edit: discount line on receipt

routes/web.php                           # + finance/discounts route (permission middleware)
config/permissions.php                   # + discounts module
tests/Feature/Discounts/                 # service + Livewire + regression specs
```

**Structure Decision**: Single Laravel monolith — feature lands entirely inside existing `Admin/Finance` domain paths; no new base directories (constitution/Technical Constraints).

## Complexity Tracking

> No constitution violations — table unused.
