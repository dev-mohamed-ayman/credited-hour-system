# Quickstart: Student Discounts

**Feature**: `specs/003-student-discounts` | Runnable validation guide proving the feature works end-to-end. Details in [data-model.md](./data-model.md) and [contracts/](./contracts/); rule sources in [research.md](./research.md) (R1–R13).

## Prerequisites

- PHP 8.4 + Composer, MySQL running locally (Herd), Node for asset build.
- Repo dependencies already installed — **no new packages** (R11: native CSV; constitution bans new deps).
- Existing finance schema present (`student_fee_tickets`, `registration_fees`, `additional_fees`, `student_wallets`, `settings` with ministerial receipt range).

## Setup

```bash
composer install
npm install && npm run build            # Sneat/Bootstrap assets via Vite
php artisan migrate --force             # 3 additive tables + 2 columns on student_fee_tickets
php artisan db:seed --class=PermissionsSeeder --force   # idempotent: adds discounts.*
php artisan optimize:clear              # refresh permission/sidebar caches
```

Grant `discounts.*` permissions to a staff user from the users screen; log in to the admin portal. Extend `DemoDataSeeder` with a discount-eligible student + a fee for a full manual walkthrough.

## Automated validation (definition of done)

```bash
php artisan test --compact --testsuite=Feature   # tests/Feature/Discounts/*
vendor/bin/pint --dirty --format agent           # must be clean
```

### Test map (each proves a spec FR/SC — constitution V)

| Test | Proves | Spec |
|------|--------|------|
| `DiscountServiceTest::eligibleFor` | scope/year/semester match; revoked excluded (no expiry) | FR-005, R1 |
| `DiscountServiceTest::planApplication` | full + **partial drawdown**, two discounts oldest-first, cap at original, percentage half-up rounding | FR-006/007/008/019, Q1 |
| `DiscountServiceTest::applyToTicket` | ticket stores original/discount/net; usages written; remaining/status updated | FR-005, BR-6 |
| `DiscountServiceTest::concurrency` | two interleaved applications ⇒ exactly one succeeds (unique index + remaining guard) | FR-010, SC-002, R3 |
| `DiscountServiceTest::revoke` | remainder revoked; paid application untouched; reason required | FR-016/017, R1 |
| `DiscountServiceTest::applyToPendingTicket` | pending re-priced; paid never touched | FR-015, R7 |
| `FeeIssuanceTest` (with discount) | issuance ⇒ `assertDatabaseHas` original/discount/net + toast | FR-005 |
| `FeeIssuanceTest` (no discount) | `original_amount=null`, `discount_amount=0` — no regression | SC-003 |
| `FeePaymentTest::zeroNet` | manual confirm, **no ministerial number consumed**, **no 0-value WalletTransaction** | Q3, R6 |
| `FeePaymentTest::br13_regression` | after zero-net paid ⇒ `checkFeeGate` passes, `outstandingTotal` excludes it | FR-014, BR-13 |
| `DiscountsIndexTest` | 403 per permission; grant/revoke; edit gated to active+zero-usages; CSV all-or-nothing | FR-021, R9, R11 |
| `StudentFinancialStatusTest` | four figures reconcile to ticket snapshots | FR-023 |

## Manual walkthrough (browser)

1. **Grant**: `/finance/discounts` → search student "E123456" → add fixed 500 EGP on registration fees, semester 1 / 2025-2026, reason + decision number → active immediately (no approval).
2. **Issue**: `finance/fee-issuance` → the fee shows a "خصم متاح" badge and expected net; generate a 1,200 EGP ticket → stored 1,200 / 500 / 700; discount → exhausted (balance 0).
3. **Pay**: `finance/fee-payment` → lines original 1,200 / discount 500 / net 700; confirm on 700; printed receipt shows "خصم: 500 ج.م — السبب: …".
4. **Zero-net**: grant 1,000 on a 1,000 fee → issue → pay with manual confirmation, no ministerial number; verify the student is **not** blocked by `checkFeeGate` afterward.
5. **Pending predates discount**: issue a 1,200 pending ticket, then grant 500 → nothing changes automatically; use "تطبيق على الحافظة القائمة" → re-priced to 700 with a logged change.
6. **Revoke paid**: try to revoke a discount whose ticket is paid → rejected, points to manual settlement.
7. **Report**: open the term discounts report → per-row student/type/applied/reason/decision/granter/date, totals reconcile to ticket snapshots.

## Legacy import (optional — R13)

```bash
php artisan discounts:import-legacy    # sandbox first; skips wallet-gift + dead 'خدمات تعليمية' rows
```

## Rollback

```bash
php artisan migrate:rollback           # drops 3 tables + 2 ticket columns (additive, complete down())
```
Review any tickets carrying applied discounts before rollback (rare — new feature).
