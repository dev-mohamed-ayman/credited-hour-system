# Contract: Routes × Permissions Matrix

**Feature**: Student Discounts | All routes live in the existing admin group of `routes/web.php` (single-file routing convention). Every route is middleware-gated; every Livewire write action re-checks server-side with `abort_unless(auth()->user()->can(...), 403)`; Blade hides via `@can` (constitution III — UI hiding is never the only guard).

## New Route — Discounts Screen (full-page Livewire)

| Method | URI | Name | Component | Middleware |
|--------|-----|------|-----------|------------|
| GET | `/finance/discounts` | `admin.finance.discounts` | `Livewire\Admin\Finance\Discounts\Index` | `permission:discounts.view` |

All discount actions are Livewire methods on that component, not routes; each re-checks its own permission:

| Action (Livewire method) | Permission | Server-side guard |
|--------------------------|------------|-------------------|
| Grant discount (modal submit) | `discounts.create` | `abort_unless` in action |
| Edit discount (active + zero usages only, R9) | `discounts.edit` | `abort_unless` + service typed exception |
| Revoke discount (reason required) | `discounts.revoke` | `abort_unless` + service state check |
| Apply to existing pending ticket (R7) | `discounts.edit` | `abort_unless` + pending/scope validation in service |
| CSV bulk import | `discounts.create` | `abort_unless` + all-or-nothing validation |
| View list / filters / application history | `discounts.view` | route middleware + `mount()` check |

## New Permission Module (`config/permissions.php`)

```php
'discounts' => [
    'label' => 'خصومات الطلاب',
    'actions' => ['view' => 'عرض', 'create' => 'إنشاء', 'edit' => 'تعديل', 'revoke' => 'إلغاء'],
],
```

Seeded idempotently by `PermissionsSeeder`; **never auto-granted** — safe launch: with no grants, no discount exists and every ticket keeps `discount_amount = 0` (zero behavioral change to existing finance flows).

## Existing Routes Touched (no URI/permission changes)

| Route | Component | Change |
|-------|-----------|--------|
| `finance/fee-issuance` | `FeeIssuance` | discount preview badges + application inside `generateTickets()` transaction (R4) — gated by existing `finance` permissions |
| `finance/fee-payment` | `FeePayment` | original/discount/net lines + zero-net confirmation path (R6) — existing `finance` permissions |
| `finance/financial-status` | `StudentFinancialStatus` | discount column read from ticket snapshots (FR-023) |
| `finance/daily-payments` | `DailyPayments` | discount line surfaced (FR-023) |
| print-tickets view | Blade | receipt shows "خصم: X ج.م — السبب: …" (FR-012) |

Applying discounts at issuance requires **no new permission** — it inherits the `finance` rights already gating those screens (R10).

## Sidebar

Link "خصومات الطلاب" inside the existing finance group (adjacent to fee-issuance), wrapped in `@can('discounts.view')`.

## Console (optional, manual — R13)

| Command | Behavior |
|---------|----------|
| `php artisan discounts:import-legacy` | Imports unconsumed legacy `students_discounts` rows; maps دراسية→registration, اخرى→additional, ادارية→fixed-on-admin-fee; **skips** محفظة (wallet gift, Q5) and خدمات تعليمية (dead code); prints per-type summary; never runs automatically |
