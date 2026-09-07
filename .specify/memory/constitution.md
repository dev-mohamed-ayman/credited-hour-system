<!--
Sync Impact Report
==================
Version change: (unversioned core scaffold, no prior project constitution) → 1.0.0
Modified principles: N/A — initial ratification. Template placeholders [PRINCIPLE_1..5_NAME/DESCRIPTION]
  replaced by the five project principles:
    I.   Money Integrity Is Sacred (NON-NEGOTIABLE)
    II.  Thin Livewire, Real Services
    III. Permission-Gated Access — No Roles
    IV.  Arabic-First, Bootstrap-Only UX
    V.   Verifiable Change — Test-First for Money & Permissions
Added sections:
  - "Technical Constraints" (fills [SECTION_2_NAME/CONTENT])
  - "Workflow & Quality Gates" (fills [SECTION_3_NAME/CONTENT])
  - Governance rules ratified from project-provided text (amendment procedure, semantic versioning policy,
    compliance review synthesized from Principle V + Workflow & Quality Gates).
Removed sections: none — all template placeholder sections were resolved, none dropped.
Follow-up TODOs: none. RATIFICATION_DATE set to 2026-09-08 (date the project-specific constitution was
  first adopted into .specify/memory/constitution.md; no earlier adoption evidence exists in git history).
-->

# Credited Hour System Constitution

## Core Principles

### I. Money Integrity Is Sacred (NON-NEGOTIABLE)

The student wallet is a financial ledger; correctness outranks every other concern.

- All balance mutations go through `App\Services\WalletService` (deposit/withdraw/refund) — never increment/decrement/assignment on `StudentWallet` from controllers, components, or seeders.
- Every money movement happens inside `DB::transaction` and writes a `WalletTransaction` row with type, reason, polymorphic reference (Registration / StudentFeeTicket), and performed_by (User / AcademicAdvisor). A balance change without a matching transaction row is a bug.
- Registration billing is idempotent delta-based: `settle()` charges only `targetCost − charged_amount`. Re-approval, re-save, or replayed requests must move zero money. Registration cost is always Σ(course hours) × hour_payment + ministerial_payment from `RegistrationFee` (department + level).
- Rejection, cancellation, and department transfer must fully reverse money (`refundAll`, `reversal_snapshot`); refunds are never partial unless the spec explicitly says so.
- Insufficient funds throw `InsufficientWalletBalanceException`; domain violations throw typed exceptions (`TransferRequestException`), never generic strings.
- Money columns are `decimal(10,2)`; never floats in persistence. Display uses `number_format($x, 2)` + ج.م.

### II. Thin Livewire, Real Services

Business logic lives in services; components orchestrate. This keeps money-critical flows testable outside the HTTP/UI layer.

- Business logic lives in `app/Services/` (`CourseRegistrationService`, `RegistrationBillingService`, `StudentTransferService`, `WalletService`, …). Livewire components are orchestration: state, validation, authorization, and delegating to a service.
- Services return result arrays (`['success' => bool, 'message' => string, …]`) for user-facing flows and throw domain exceptions for invariant violations.
- Livewire components validate in-component (`rules()`/`messages()` or inline `$this->validate`) — Form Requests are reserved for classic `Http/Controllers` CRUD. This deliberately overrides generic "always Form Requests" guidance.
- Full-page components only, routed directly (`Route::get(..., Index::class)`), rendering via `->extends('<portal>.layouts.app')->section('content')`. Naming mirrors: `App\Livewire\Admin\<Domain>\Index` ↔ `livewire/admin/<domain>/index.blade.php`.
- Use property hooks (`updatingX()`), computed properties, `WithPagination`, `loadMissing` to avoid N+1, and `wire:model.live` / `wire:key` / `dispatch()` conventions already established.

### III. Permission-Gated Access — No Roles

Authorization is Spatie direct permissions only. Roles are forbidden; never introduce or suggest them.

- Permissions follow `{module}.{action}` (`students.view`, `course_registrations.approve`, `student_transfers.decide`) and are defined centrally in `config/permissions.php` with Arabic labels, seeded idempotently by `PermissionsSeeder`.
- Enforcement is layered and mandatory: route middleware (`->middleware('permission:x.view')`), server-side checks inside actions/`mount()` (`abort_unless($user->can(...), 403)`), and `@can` in Blade. UI hiding is never the only guard.
- Three independent guards — `web` (staff), `advisor` (`AcademicAdvisor`), `student` (`Student`) — with per-portal login, layout, and redirect logic in `bootstrap/app.php`. Cross-portal access is a 403, and advisor actions must additionally verify student ownership.
- Super-admin bypass exists only via the `Gate::before` hook; do not scatter `is_super_admin` conditionals.

### IV. Arabic-First, Bootstrap-Only UX

The product serves an Arabic-speaking, RTL institutional audience on a purchased template; UI consistency is a requirement, not a preference.

- All user-facing text is hardcoded Arabic (enum `label()`, validation `messages()`, service messages, Blade). Do not migrate to `__()`/lang files unless the project explicitly decides to.
- RTL + the purchased Bootstrap 5 (Sneat) template in `public/assets` is canonical. Tailwind v4 is installed but not the UI system — do not restyle pages with Tailwind utilities or introduce Blade x-components without approval.
- Feedback is toast-only via the toast/success/error Livewire event bridge to SweetAlert2 (`window.toast()`), and destructive actions confirm via `window.confirmAction()`. Never `alert()`/`confirm()`/flash-only patterns.
- Status is surfaced through backed enums with `label()` (+ `badgeClass()` where defined) cast on models — never raw string comparisons in views.

### V. Verifiable Change — Test-First for Money & Permissions

Pest 4 feature tests are the definition of done for any change touching billing, registration state, transfers, or authorization.

- Pest 4 feature tests are the definition of done for any change touching billing, registration state, transfers, or authorization. Livewire component tests (`Livewire::actingAs($user, $guard)->test(...)`) are the primary integration surface — exercise the same path users do.
- Reuse the global fixture builders in `tests/Pest.php` (`billingWorld()`, `transferWorld()`, `fundWallet()`, `issueTicket()`, `chargedRegistration()`) instead of re-handcrafting worlds; opt into `RefreshDatabase` per file.
- Assert outcomes, not implementation: wallet balance deltas, `charged_amount`, transaction rows, statuses, and Arabic UI copy (`assertSee('...')`); exception messages via `->throws(Exception::class, 'رسالة عربية')`.
- Do not claim a billing/transfer change complete without a regression test proving idempotency (double-settle moves no money) and reversal (reject/transfer refunds exactly what was charged).

## Technical Constraints

- Stack locked: PHP 8.4, Laravel 12, Livewire 3, Spatie Permission 8, Pest 4, Pint, Vite 7 + Bootstrap/Sneat assets. No Filament, Inertia, Sanctum, API layer, queues, or new base directories without approval.
- Database: MySQL locally, sqlite in-memory for tests. Big-increment IDs (no UUIDs), `timestamps()` everywhere, soft deletes only where already present (`students`, `academic_advisors`). Migrations are additive (`add_x_to_y_table`) with complete `down()` (explicit `dropForeign` + `dropColumn`); FKs use `cascadeOnDelete` for owned rows, `nullOnDelete` for audit refs, `restrictOnDelete` for referenced academic data. Enums stored as string with model casts.
- PHP style: Pint-formatted (`vendor/bin/pint --dirty` before finishing), curly braces always, explicit return types, constructor promotion in services, `casts()` method on models (Laravel 12 style), PHPDoc with array shapes for service contracts.
- Known legacy, do not "fix" silently: `students.plain_password` is stored intentionally for counter support; `semester` is an Arabic string mapped via `CourseSemesterMapper`; orphan artifacts (`print_certificate.blade.php`, `Livewire/scratch/`, empty dirs) stay until explicitly removed.

## Workflow & Quality Gates

- Follow existing `routes/web.php` single-file routing with named routes; new admin features register permissions → routes with middleware → Livewire/controller → views, in that order.
- New features start from a written spec (root `*.md` specs / `docs/superpowers/specs/`) before code, matching the Lecture Scheduling precedent.
- Before any change is considered done: `php artisan test --compact` passes, `vendor/bin/pint --dirty --format agent` is clean, and `composer run dev` reflects UI changes (build step if needed).
- Commits follow the existing conventional style: `feat(scope): …`, `fix: …`, `refactor: …` on `main`.

## Governance

This constitution supersedes ad-hoc habits and the generic guidance in AGENTS.md/CLAUDE.md wherever they conflict (notably: Livewire in-component validation, no Form Requests for components, and no roles). The detailed how-to lives in `.agents/rules/{livewire-patterns,permissions,ux-standards}.md`; those files must stay consistent with this document.

- **Supremacy**: where this document conflicts with AGENTS.md, CLAUDE.md, or ad-hoc practice, this constitution wins.
- **Compliance review**: every change is reviewed against these principles; changes touching billing, registration state, transfers, or authorization MUST pass the regression tests required by Principle V and the quality gates in Workflow & Quality Gates before being considered done.
- **Amendment procedure**: amendments require (1) a concrete failing/conflicting scenario motivating the change, (2) update of this file with a version bump, (3) sync of `.agents/rules` and affected tests.
- **Versioning policy**: versioning is semantic — MAJOR = principle removed/redefined incompatibly, MINOR = principle added or materially expanded, PATCH = clarifications and wording.

**Version**: 1.0.0 | **Ratified**: 2026-09-08 | **Last Amended**: 2026-09-08
