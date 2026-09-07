# Implementation Plan: Lecture & Venue Scheduling

**Branch**: `001-lecture-scheduling` (spec-kit nominal; repo convention is to work on `main` per constitution) | **Date**: 2026-09-08 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/001-lecture-scheduling/spec.md`

**Supplementary source**: `Lecture Scheduling — Implementation Plan (How).md` (repo root) — repository audit, migration sketches, service signature, and build order; reconciled with the 5 clarifications from spec Session 2026-09-08.

## Summary

Add lecture/venue scheduling to the Credit Hour System: admins register venues (name, kind, capacity, active status) and attach lecture sessions to courses — venue + weekday + start/end time + attending sections — with server-enforced capacity, venue-conflict, and section-conflict rules scoped to the same academic year + semester. All rules live in a new `LectureScheduleService` (thin-Livewire convention); venue CRUD follows the existing `SectionController` controller+Blade pattern; session management is a reactive Livewire screen with live capacity totals and a "from/to" section-range picker. Three additive migrations, two enums, two models + pivot, eight new Spatie permissions. No new packages, no changes to existing tables.

## Technical Context

**Language/Version**: PHP 8.4, Laravel 12

**Primary Dependencies**: Livewire 3, Spatie Permission 8 (existing) — **zero new packages** (`composer.json`/`package.json` untouched)

**Storage**: MySQL (local/dev via Herd); sqlite in-memory for tests

**Testing**: Pest 4 (`php artisan test --compact`), Pint for style

**Target Platform**: Server-rendered RTL admin web app on the purchased Bootstrap 5 (Sneat) template in `public/assets`

**Project Type**: Web application — single Laravel app, admin portal feature (no API layer)

**Performance Goals**: Session creation flow < 1 minute (SC-003); conflict checks as single indexed queries (no N+1); weekly grid renders comfortably at university scale (hundreds of sessions per term)

**Constraints**: Additive migrations only (complete `down()`); Arabic-only UI copy; toast/confirm JS bridge; permission-gated every action; no roles; no Tailwind restyle; no new base directories

**Scale/Scope**: 3 new tables, 2 enums, 2 models, 1 service + 1 exception, 1 controller + 2 Form Requests, 3 Livewire components, 8 permissions, ~2 view directories

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Status | Rationale |
|-----------|--------|-----------|
| I. Money Integrity Is Sacred | ✅ PASS (N/A) | Feature touches no wallet, billing, or registration-cost flow. No `WalletService`/transaction involvement. |
| II. Thin Livewire, Real Services | ✅ PASS | All BR rules (capacity, conflicts, membership) live in `App\Services\LectureScheduleService`; Livewire `Form`/`Index` only orchestrate state, validation, authorization. Livewire validates in-component; venue CRUD uses Form Requests — exactly the split the constitution mandates. Full-page components routed directly, `->extends('admin.layouts.app')`. |
| III. Permission-Gated Access — No Roles | ✅ PASS | New `venues.*` and `lecture_schedules.*` permissions in `config/permissions.php` (Arabic labels) + idempotent `PermissionsSeeder`; route middleware + `abort_unless(...)` in actions + `@can` in sidebar. No policies, no roles. |
| IV. Arabic-First, Bootstrap-Only UX | ✅ PASS | All messages/conflict copy in Arabic; views clone existing `admin/pages/section` and Sneat structures; feedback via toast bridge; statuses via backed enums with `label()`. |
| V. Verifiable Change — Test-First | ✅ PASS | Pest feature tests cover every rule (FR-005..FR-013, FR-020) at service level first, then Livewire/permission level (403s, DB assertions, Arabic copy). Permission surface ⇒ tests are mandatory, and they are planned (see quickstart.md). |
| Technical Constraints | ✅ PASS | Big-increment IDs, `timestamps()`, enums as string casts, `casts()` method style, composite indexes for conflict queries, FK policy: `cascadeOnDelete` for owned rows (course→sessions, pivot), `restrictOnDelete` for referenced data (venue), `nullOnDelete` for audit-ish refs (year). No stack changes. |
| Workflow & Quality Gates | ✅ PASS | Order: permissions → routes+middleware → components/controller → views. Verification: `php artisan test --compact` + `vendor/bin/pint --dirty` + build step for any asset change. |

**Evaluation**: PASS — no violations; Complexity Tracking not required.

**Post-Phase-1 re-check**: PASS — design artifacts (data-model.md, contracts/, research.md) introduce no roles, no new packages, no money paths, no Tailwind restyle, and keep all business rules in the service layer.

## Project Structure

### Documentation (this feature)

```text
specs/001-lecture-scheduling/
├── plan.md              # This file
├── research.md          # Phase 0 output — decisions, rationale, alternatives
├── data-model.md        # Phase 1 output — entities, fields, invariants
├── quickstart.md        # Phase 1 output — validation/run guide
├── checklists/
│   └── requirements.md  # Spec quality checklist (16/16 passing)
├── contracts/           # Phase 1 output — interface contracts
│   ├── routes.md        # Route × permission matrix
│   ├── venue-crud.md    # Controller/Form Request field contracts
│   └── scheduling-ui.md # Livewire component behavior contracts
└── tasks.md             # Phase 2 output (/speckit.tasks — NOT created here)
```

### Source Code (repository root)

```text
app/
├── Enums/
│   ├── VenueType.php                    # NEW: Auditorium|Lab|Classroom|Other + Arabic label()
│   └── DayOfWeek.php                    # NEW: Saturday..Thursday + label() + order()
├── Models/
│   ├── Venue.php                        # NEW: HasDeletionGuards(lectureSchedules)
│   ├── LectureSchedule.php              # NEW: course/venue/year/sections, overlaps()
│   ├── Course.php                       # EDIT: + lectureSchedules() + blocking relation
│   └── Section.php                      # EDIT: + lectureSchedules() + blocking relation
├── Services/
│   └── LectureScheduleService.php       # NEW: all validation + create/update (transaction)
├── Exceptions/
│   └── LectureScheduleConflictException.php  # NEW: typed, Arabic message ready
├── Http/
│   ├── Controllers/Admin/VenueController.php     # NEW: resource except show
│   └── Requests/Admin/
│       ├── StoreVenueRequest.php        # NEW
│       └── UpdateVenueRequest.php       # NEW
└── Livewire/Admin/LectureSchedule/
    ├── Index.php                        # NEW: course picker + session list + review flags
    ├── Form.php                         # NEW: create/edit session, live capacity, range
    └── WeekGrid.php                     # NEW: day × time grid (course-centric)

database/
├── migrations/
│   ├── ____create_venues_table.php
│   ├── ____create_lecture_schedules_table.php
│   └── ____create_lecture_schedule_section_table.php
└── factories/
    ├── VenueFactory.php                 # NEW (+ auditorium()/lab() states)
    └── LectureScheduleFactory.php       # NEW

resources/views/
├── admin/pages/venue/{index,create,edit}.blade.php      # NEW (clone section pages)
├── livewire/admin/lecture-schedule/{index,form,week-grid}.blade.php  # NEW
└── admin/layouts/sidebar.blade.php                      # EDIT: 2 links under courses group

config/permissions.php                                   # EDIT: venues + lecture_schedules
routes/web.php                                           # EDIT: venues resource + 3 schedule routes
tests/Feature/LectureSchedule/                           # NEW: service + Livewire + venue CRUD specs
```

**Structure Decision**: Single Laravel app (existing structure). No new base directories — every file lands in an established home (`Admin/` namespaces, `admin/pages/`, `livewire/admin/`, `tests/Feature/`).

## Complexity Tracking

> No constitution violations — table intentionally empty.
