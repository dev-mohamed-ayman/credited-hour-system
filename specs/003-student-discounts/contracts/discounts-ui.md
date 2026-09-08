# Contract: Discounts Admin Screen (`Livewire\Admin\Finance\Discounts\Index`)

**Feature**: Student Discounts | Full-page Livewire component at `/finance/discounts`, extending the admin layout. Thin component — all money logic delegates to `DiscountService` (constitution II). In-component `rules()`/`messages()` validation, Arabic copy, Sneat/Bootstrap markup, toast via the existing event bridge (constitution IV).

## Public State

| Property | Type | Purpose |
|----------|------|---------|
| `$search` | string | student name/code filter, `wire:model.live` |
| `$statusFilter` | ?DiscountStatus | list filter |
| `$scopeFilter` | ?DiscountScope | list filter |
| `$yearFilter`, `$semesterFilter` | mixed | list filters |
| `$showCreateModal`, `$showRevokeModal`, `$editingId` | bool/int | modal toggles |
| Create/edit form fields | — | `mode`, `value`, `scope`, `fee_id`, `year_id`, `semester`, `reason`, `decision_number`, `student_id` |

## Behaviors (each maps to a spec FR + a test)

| Behavior | FR | Contract |
|----------|----|----------|
| Search students by code/name | FR-001 | paginated table (`WithPagination`), `loadMissing` to avoid N+1 |
| Grant discount (fixed or percentage, scoped, optional year/semester, reason, decision number) | FR-001, FR-002, FR-026 | `save()` → validate → `abort_unless(can('discounts.create'))` → `DiscountService::grant()` → `granted` event → toast; becomes active immediately (no approval) |
| Show status badge + applied/remaining | FR-003 | `DiscountStatus::label()` + `badgeClass()`; percentage shows no balance (Q2) |
| Edit discount | FR-020, R9 | edit button rendered only when `status=active && usages()->count()===0`; `DiscountService::update()` writes `edited` event with old/new |
| Revoke with mandatory reason | FR-003, FR-016, FR-017 | `window.confirmAction()`; `DiscountService::revoke()` cancels only remaining balance; paid applications untouched; toast |
| "تطبيق على الحافظة القائمة" (apply to pending) | FR-015, R7 | button shown only when an eligible pending ticket exists; `DiscountService::applyToPendingTicket()` re-prices + logs |
| CSV import | FR-024, R11 | `WithFileUploads`; per-row error table on failure (whole file rejected); success toast with count |
| Application/audit history | FR-020, FR-022 | tab merging `student_discount_events` + `student_discount_usages` into one timeline |
| Permission gating | FR-021 | `mount()` + every action `abort_unless(...->can('discounts.<action>'), 403)` |

## Validation (`rules()` + Arabic `messages()`)

- `value`: `required|numeric|min:0.01`; when `mode=percentage` add `max:100`.
- `scope`: `required|in:registration,additional,any`; `fee_id` `required_if:scope,additional` + must be an `AdditionalFee`.
- `reason`: `required|string|max:255`.
- `decision_number`: `nullable|string|max:255`.
- `year_id`/`semester`: `nullable` (null ⇒ open scope; no expiry — R1).
- Revoke `revoked_reason`: `required|string|max:255`.

## Out of Screen (delegated)

- Eligibility detection, allocation math, locking, status transitions, wallet zero-guard — all in `DiscountService` / `FeeIssuance` / `FeePayment` (see [finance-integration.md](./finance-integration.md)).
- Student portal display of discounts — phase 2, not built (Out of Scope).
