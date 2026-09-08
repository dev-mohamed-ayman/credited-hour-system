# Implementation Plan: Exam Scheduling, Committees & Seating

**Branch**: `002-exam-scheduling` (spec-kit nominal; repo convention is to work on `main` per constitution) | **Date**: 2026-09-08 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/002-exam-scheduling/spec.md`

**Supplementary source**: `Exam Scheduling — Implementation Plan (How).md` (repo root) — repository audit, migration sketches, service signatures, and build order; reconciled with the 7 clarifications from spec Session 2026-09-08 (notably: CSV import **deferred**, regeneration **preserves** manual placements, publishing is **per session**, exam window is an **optional guard**, concurrent saves get an **absolute guarantee**).

## Summary

Add first-class exam scheduling to the Credit Hour System on top of the existing approved registrations: admins pick a year+semester and see exactly the courses with approved registrations, define exam sessions (date, start/end, type Regular/Resit/Improvement) with hard conflict rules — no student double-booked, no venue/committee double-booked, one session per course+year+semester+type, dates inside the optional per-semester exam window — then define committees (venue + capacity) and generate a stable alphabetical seating distribution with per-committee seat numbers. Regeneration is incremental (manual placements survive; newcomers are absorbed; ineligible students are removed). Nothing is visible to students until a session passes the publish gate and is individually published; edits revert it to draft. Students see a chronological personal exam table (dashboard block + printable page); admins print per-committee attendance sheets. Three new tables + nullable exam-window columns on `years`, two enums, three models, two services (`ExamScheduleService`, `ExamSeatingService`), three admin Livewire screens + one student block + one print controller, five new permissions. No new packages, no CSV import in this phase, venues reused as-is from the delivered lecture-scheduling feature.

## Technical Context

**Language/Version**: PHP 8.4, Laravel 12

**Primary Dependencies**: Livewire 3, Spatie Permission 8 (existing) — **zero new packages** (`composer.json`/`package.json` untouched; CSV/Excel packages not needed since import is deferred)

**Storage**: MySQL (local/dev via Herd); sqlite in-memory for tests

**Testing**: Pest 4 (`php artisan test --compact`), Pint for style

**Target Platform**: Server-rendered RTL web app on the purchased Bootstrap 5 (Sneat) template in `public/assets` — admin portal (staff guard) + student portal (student guard)

**Project Type**: Web application — single Laravel app, no API layer

**Performance Goals**: Conflict detection as single indexed queries (no N+1) even at thousands of students × dozens of courses (SC-001, risk R1); full-term schedule prepared in hours of admin time; interactive-time generation for a session of up to ~2,000 examinees (validated via quickstart step 4, no dedicated perf task); board and distribution tables paginated

**Constraints**: Additive migrations only (complete `down()`); Arabic-only UI copy; toast/confirm JS bridge; permission-gated every action; no roles; no Tailwind restyle; no new base directories; conflict rules enforced atomically at save time (absolute guarantee, clarification 2026-09-08)

**Scale/Scope**: 3 new tables + 6 nullable columns on `years`, 2 enums, 3 models, 2 services + 1 exception, 1 print controller, 4 Livewire components (3 admin + 1 student), 5 permissions, ~3 view directories; thousands of students per term, tens of sessions per term

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Status | Rationale |
|-----------|--------|-----------|
| I. Money Integrity Is Sacred | ✅ PASS (N/A) | Feature touches no wallet, billing, or registration-cost flow. Read-only use of `Registration`/`RegistrationCourse` as the examinee audience source; no balance mutations anywhere. |
| II. Thin Livewire, Real Services | ✅ PASS | All rules (audience, conflicts, capacity, distribution, publish gate) live in `App\Services\ExamScheduleService` and `App\Services\ExamSeatingService`; Livewire components only orchestrate state, validation, authorization, and delegate. In-component validation via `rules()`/`messages()`; full-page components routed directly with `->extends('<portal>.layouts.app')`. One classic controller only for print pages (no interactivity). |
| III. Permission-Gated Access — No Roles | ✅ PASS | New `exam_schedules` module (view/create/edit/delete/publish) in `config/permissions.php` with Arabic labels + idempotent `PermissionsSeeder`; route middleware + `abort_unless($user->can(...), 403)` in actions + `@can` in sidebar; student views gated by the existing `student` guard, not permissions. No policies, no roles. |
| IV. Arabic-First, Bootstrap-Only UX | ✅ PASS | All messages, conflict reports, enum `label()`s in Arabic; views clone existing Sneat structures (`admin/pages/student/print_seat_number.blade.php` pattern for print); feedback via toast bridge; statuses via backed enums (`ExamSessionStatus`, `ExamType`) cast on models. |
| V. Verifiable Change — Test-First | ✅ PASS | Authorization surface ⇒ Pest feature tests are mandatory: service-level tests for every rule (FR-003..FR-010, FR-012..FR-018), Livewire tests for 403s/publish flows/stale badges, student-visibility tests (published-only), print smoke tests. Planned in quickstart.md. |
| Technical Constraints | ✅ PASS | Big-increment IDs, `timestamps()`, enums as string with `casts()` method, composite indexes for conflict queries; FK policy: `cascadeOnDelete` for owned rows (course→sessions, session→committees/assignments), `restrictOnDelete` for referenced academic data (venue, year), mirroring the delivered `lecture_schedules` migration. Seat numbers stored as string (legacy-compatible). |
| Workflow & Quality Gates | ✅ PASS | Order: permissions → routes+middleware → services → components/controller → views. Verification: `php artisan test --compact` + `vendor/bin/pint --dirty` before done. Spec-first workflow satisfied (this plan follows the clarified spec). |

**Evaluation**: PASS — no violations; Complexity Tracking not required.

**Post-Phase-1 re-check**: PASS — design artifacts (research.md, data-model.md, contracts/) introduce no roles, no new packages, no money paths, no Tailwind restyle, keep all business rules in the service layer, and defer CSV import rather than adding dependencies.

## Project Structure

### Documentation (this feature)

```text
specs/002-exam-scheduling/
├── plan.md              # This file
├── research.md          # Phase 0 output — decisions, rationale, alternatives
├── data-model.md        # Phase 1 output — entities, fields, invariants
├── quickstart.md        # Phase 1 output — validation/run guide
├── checklists/
│   └── requirements.md  # Spec quality checklist (16/16 passing)
├── contracts/           # Phase 1 output — interface contracts
│   ├── routes.md        # Route × permission matrix
│   ├── admin-ui.md      # Admin Livewire component behavior contracts
│   └── student-view.md  # Student dashboard block + print page contracts
└── tasks.md             # Phase 2 output (/speckit.tasks command - NOT created by /speckit.plan)
```

### Source Code (repository root)

```text
app/
├── Enums/
│   ├── ExamType.php                          # NEW: Regular|Resit|Improvement + Arabic label()
│   └── ExamSessionStatus.php                 # NEW: Draft|Published + label() + badgeClass()
├── Models/
│   ├── ExamSession.php                       # NEW: HasDeletionGuards, overlaps(), casts()
│   ├── ExamCommittee.php                     # NEW: venue/name/capacity, assignedCount(), isFull()
│   ├── ExamSeatAssignment.php                # NEW: thin pivot-with-seat-number
│   ├── Course.php                            # EDIT: + examSessions() + blocking relation
│   ├── Year.php                              # EDIT: + examSessions() + blocking relation + exam-window accessors
│   └── Venue.php                             # EDIT: $blockingRelations += ['examCommittees']
├── Services/
│   ├── ExamScheduleService.php               # NEW: examinees(), validateSession(), findStudentConflicts(),
│   │                                         #      assertPublishable(), publish(), unpublish()
│   └── ExamSeatingService.php                # NEW: generateDistribution() (incremental), moveStudent(),
│                                             #      isSeatingStale(), seat-number format seam
├── Exceptions/
│   └── ExamScheduleException.php             # NEW: typed, Arabic messages ready for display
├── Http/
│   └── Controllers/Admin/ExamPrintController.php  # NEW: committeeSheet(), studentSchedule() (browser print)
└── Livewire/
    ├── Admin/ExamSchedule/
    │   ├── Index.php                         # NEW: year+semester board of courses w/ approved registrations
    │   ├── Form.php                          # NEW: session CRUD + committees repeater + live capacity
    │   └── Seating.php                       # NEW: distribution tabs, generate, manual moves, stale badge
    └── Student/
        └── ExamSchedule.php                  # NEW: published-only personal exam table (dashboard block)

database/
├── migrations/
│   ├── ____create_exam_sessions_table.php            # NEW
│   ├── ____create_exam_committees_table.php          # NEW
│   ├── ____create_exam_seat_assignments_table.php    # NEW
│   └── ____add_exam_periods_to_years_table.php       # NEW: 6 nullable DATE columns
└── factories/
    ├── ExamSessionFactory.php                # NEW: published(), resit(), improvement() states
    ├── ExamCommitteeFactory.php              # NEW
    └── ExamSeatAssignmentFactory.php         # NEW

resources/views/
├── livewire/admin/exam-schedule/{index,form,seating}.blade.php   # NEW
├── livewire/student/exam-schedule.blade.php                      # NEW
├── admin/pages/exam/{committee-sheet,student-schedule}.blade.php # NEW (print, print_seat_number pattern)
└── livewire/student/dashboard.blade.php                          # EDIT: mount student block

config/permissions.php                        # EDIT: exam_schedules module (5 actions)
routes/web.php                                # EDIT: admin exam routes + print routes + student route
resources/views/admin/layouts/sidebar.blade.php  # EDIT: exam links under courses group, @can-gated
database/seeders/DemoDataSeeder.php           # EDIT: full-term exam scenario

tests/Feature/ExamSchedule/                   # NEW: service + seating + Livewire + student + print specs
```

**Structure Decision**: Single Laravel app (existing structure). No new base directories — every file lands in an established home (`Admin/ExamSchedule` mirrors `Admin/LectureSchedule` from 001; student block mirrors existing `App\Livewire\Student\*`; print views mirror `admin/pages/student/print_seat_number.blade.php`).

## Complexity Tracking

> No constitution violations — table intentionally empty.
