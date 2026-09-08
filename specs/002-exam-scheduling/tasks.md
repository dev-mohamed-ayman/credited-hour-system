---
description: "Task list for Exam Scheduling, Committees & Seating implementation"
---

# Tasks: Exam Scheduling, Committees & Seating

**Input**: Design documents from `/specs/002-exam-scheduling/`

**Prerequisites**: plan.md (required), spec.md (required for user stories), research.md (R1–R13), data-model.md, contracts/, quickstart.md

**Tests**: INCLUDED — constitution Principle V makes Pest feature tests the definition of done for any change touching authorization (new `exam_schedules.*` permissions), and quickstart.md defines the mandatory coverage map. Test tasks precede implementation within each story.

**Organization**: Tasks grouped by user story (US1–US4 from spec.md) so each story is independently implementable and testable. No CSV import anywhere (research R1 — deferred by clarification).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: US1 = sessions/conflicts, US2 = committees/distribution/seating, US3 = publish gate/student view, US4 = print sheets
- Exact file paths included per repo conventions (Laravel 12, single app)

## Phase 1: Setup (Enums & Permissions)

**Purpose**: Shared vocabulary and the permission surface (constitution workflow order: permissions first)

- [x] T001 [P] Create `ExamType` enum in `app/Enums/ExamType.php` — cases `Regular='regular'`, `Resit='resit'`, `Improvement='improvement'` with Arabic `label()` (عادي / فصل ثانٍ / تحسين) per data-model.md
- [x] T002 [P] Create `ExamSessionStatus` enum in `app/Enums/ExamSessionStatus.php` — `Draft='draft'`, `Published='published'` with Arabic `label()` + `badgeClass()`, mirroring `app/Enums/MilitaryEducationCourseStatus.php`
- [x] T003 [P] Register `exam_schedules` module in `config/permissions.php` — label `جدول الامتحانات`, actions view=عرض, create=إنشاء, edit=تعديل, delete=حذف, publish=نشر (no import action, R1); verify `PermissionsSeeder` picks it up idempotently

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Schema, models, factories, exception, routes, and exam-window settings that ALL stories need

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [x] T004 Create migration `database/migrations/____create_exam_sessions_table.php` per data-model.md §1 — columns, `unique(course_id, year_id, semester, type)`, `index(year_id, semester, exam_date)`, complete `down()`
- [x] T005 [P] Create migration `database/migrations/____create_exam_committees_table.php` per data-model.md §2 — `venue_id` restrictOnDelete, `unique(exam_session_id, name)`, complete `down()` (committees are foundational: US1 venue-conflict checks need them; committee UI ships in US2)
- [x] T006 [P] Create migration `database/migrations/____add_exam_periods_to_years_table.php` — 6 nullable DATE columns per data-model.md §4 (R6), explicit `dropColumn` in `down()`
- [x] T007 Create `app/Models/ExamSession.php` — `casts()` method (semester/type/status/exam_date), `HasDeletionGuards` with `$blockingRelations = ['committees', 'seatAssignments']`, relations `course()/year()/committees()/seatAssignments()`, `overlaps()` minute-math helper cloned from `LectureSchedule::overlaps()` pattern (R2)
- [x] T008 [P] Create `app/Models/ExamCommittee.php` — relations `examSession()/venue()/assignments()`, helpers `assignedCount(): int`, `isFull(): bool`
- [x] T009 Edit existing models: add `examSessions(): HasMany` + append `examSessions` to `$blockingRelations` in `app/Models/Course.php` and `app/Models/Year.php`; add `examCommittees(): HasMany` + append to `$blockingRelations` in `app/Models/Venue.php`; add `semesterExamWindow(Semester $semester): ?array` accessor to `app/Models/Year.php` (R6)
- [x] T010 [P] Create factories `database/factories/ExamSessionFactory.php` (states `published()`, `resit()`, `improvement()`) and `database/factories/ExamCommitteeFactory.php`
- [x] T011 [P] Create `app/Exceptions/ExamScheduleException.php` — typed exception carrying Arabic report strings/messages (constitution I exception style, mirrors `LectureScheduleConflictException`)
- [x] T012 Add routes to `routes/web.php` per `contracts/routes.md` — admin `exam-schedules` index/create/edit/seating (full-page Livewire, permission middleware) ONLY; the student route ships with T034 and print routes with T037 (their components/controllers do not exist yet in this phase)
- [x] T013 Add the 6 exam-window date fields to the Year Settings screen (`app/Livewire/Admin/YearSettings.php` + `resources/views/livewire/admin/year-settings.blade.php`) — `rules()` with from≤to per semester pair, Arabic labels "فترات الامتحانات" section (R6)

**Checkpoint**: Foundation ready — user stories can now proceed in priority order or in parallel

---

## Phase 3: User Story 1 - Conflict-free exam session scheduling (Priority: P1) 🎯 MVP

**Goal**: Admin selects year+semester, sees exactly courses with approved registrations, and creates/edits/deletes exam sessions with hard uniqueness, time/window, student-conflict, and venue-conflict rules enforced atomically at save.

**Independent Test**: Per spec US1 — pre-provision two courses with overlapping approved registrations + venues/committees at the data level; valid session saves; overlapping-student session rejected naming students; overlapping-venue session rejected naming the session; duplicate course+year+semester+type rejected; adjacency and cross-term independence accepted.

### Tests for User Story 1 (write FIRST, must fail) ⚠️

- [x] T014 [P] [US1] Service tests in `tests/Feature/ExamSchedule/ExamScheduleServiceTest.php` — audience approved-only + soft-deleted excluded (R3); student conflict: overlap rejected with names+courses, 11:00→11:00 adjacency OK, different dates OK, different year/semester independent (R2); uniqueness: duplicate Regular rejected, Resit accepted (FR-007); end≤start rejected; off-quarter-hour rejected; date outside configured window rejected with Arabic range message, unset window accepts any date (R6) — per quickstart coverage map
- [x] T015 [P] [US1] Atomic-save test in `tests/Feature/ExamSchedule/ExamScheduleAtomicSaveTest.php` — sequential double-submit: second overlapping save rejected with standard message, exactly one row persists; lock-before-check ordering asserted (R5)
- [x] T016 [P] [US1] Permission + form tests in `tests/Feature/ExamSchedule/ExamScheduleFormTest.php` — 403 for guest/permission-less on every route and Livewire action; valid create → `assertDatabaseHas` + success toast; conflict rejection → Arabic toast, no DB row (contracts/admin-ui.md)

### Implementation for User Story 1

- [x] T017 [US1] Create `app/Services/ExamScheduleService.php` — `examinees()` single query (R3), `findStudentConflicts()` single join query returning typed conflicts (R4), `validateSession()` (R2/R6 + venue overlap via committees FR-006), `create()/update()` inside `DB::transaction` with `lockForUpdate()` on the year row before checks (R5), edited session excluded from own conflict check (FR-009)
- [x] T018 [US1] Create `app/Livewire/Admin/ExamSchedule/Index.php` + `resources/views/livewire/admin/exam-schedule/index.blade.php` — year/semester defaults (`Year::current()`/`currentSemester()`), board of courses with approved registrations, examinee counts, session state badges, department/level/status filters, pagination, delete action with `confirmAction()` (contracts/admin-ui.md `Index`)
- [x] T019 [US1] Create `app/Livewire/Admin/ExamSchedule/Form.php` + `resources/views/livewire/admin/exam-schedule/form.blade.php` — create/edit session (course locked on edit), type select, date, 15-min time steppers, notes; `save()` delegates to service, typed exception → error toast with full Arabic report (no committee repeater yet — US2)
- [x] T020 [US1] Add "جدول الامتحانات" sidebar link wrapped in `@can('exam_schedules.view')` to `resources/views/admin/layouts/sidebar.blade.php` under the المواد الدراسية group next to the 001 lecture links
- [x] T021 [US1] Run `php artisan test --compact --filter=ExamSchedule` until green + `vendor/bin/pint --dirty --format agent`

**Checkpoint**: US1 fully functional — sessions exist with zero-conflict guarantees; independently demoable

---

## Phase 4: User Story 2 - Committees, automatic distribution & seating numbers (Priority: P2)

**Goal**: Committee management (venue/name/capacity), incremental alphabetical distribution with per-committee seat numbers, manual moves, capacity enforcement, and the stale-distribution flag.

**Independent Test**: Per spec US2 — 120 examinees → two committees of 60 split alphabetically with unique seat numbers; regeneration identical and preserves manual moves; newcomers absorbed, ineligible dropped; 120 vs 100 seats → exact Arabic message + zero DB change; new approval flags "توزيع غير محدّث".

### Tests for User Story 2 (write FIRST, must fail) ⚠️

- [x] T022 [P] [US2] Seating service tests in `tests/Feature/ExamSchedule/ExamSeatingServiceTest.php` — stable alphabetical split; seat numbers unique per committee and STABLE per student across regenerations (existing students keep their numbers; gaps from removed students are allowed, never renumbered — R7); regeneration keeps existing placements incl. manual moves, removes ineligible, places only unplaced (R7, SC-006); capacity overflow throws exact message "عدد الممتحنين (120) يتجاوز إجمالي سعة اللجان (100)" with rollback; `isSeatingStale()` true after new approval, false after regeneration (R9); committee capacity reduced below its placed students ⇒ stale/over-capacity true ⇒ publish blocked, no students removed (FR-018)
- [x] T023 [P] [US2] Livewire tests in `tests/Feature/ExamSchedule/ExamSeatingTest.php` — generate button happy path + overflow error toast; move student between committees incl. target-full rejection; stale badge renders; 403 without `exam_schedules.edit`

### Implementation for User Story 2

- [x] T024 [US2] Create migration `database/migrations/____create_exam_seat_assignments_table.php` per data-model.md §3 — `unique(exam_session_id, student_id)`, `unique(exam_committee_id, seat_number)`, complete `down()`
- [x] T025 [US2] Create `app/Models/ExamSeatAssignment.php` (thin, relations only) + `database/factories/ExamSeatAssignmentFactory.php`; add `seatAssignments(): HasMany` to `ExamCommittee`
- [x] T026 [US2] Create `app/Services/ExamSeatingService.php` — `generateDistribution()` incremental algorithm (R7) in a transaction, `moveStudent()` capacity-checked placement at the committee's next unused number, `isSeatingStale()` derived comparison incl. per-committee over-capacity condition (R9, FR-018), private seat-number formatter seam (R8)
- [x] T027 [US2] Extend `app/Livewire/Admin/ExamSchedule/Form.php` + `resources/views/livewire/admin/exam-schedule/form.blade.php` — committees repeater (active venues only, name, capacity ≥1, unique names) + live examinees-vs-Σcapacity counter card (`wire:model.live`) per contracts/admin-ui.md
- [x] T028 [US2] Create `app/Livewire/Admin/ExamSchedule/Seating.php` + `resources/views/livewire/admin/exam-schedule/seating.blade.php` — committee tabs, paginated assignment tables (seat/code/name/section), generate + move actions, stale badge with inline CTA, empty states (contracts/admin-ui.md `Seating`)
- [x] T029 [US2] Run `php artisan test --compact --filter=Exam` until green + `vendor/bin/pint --dirty --format agent`

**Checkpoint**: US1+US2 both functional — full schedule with rooms and seats exists behind draft state

---

## Phase 5: User Story 3 - Publish gate & personal student exam table (Priority: P3)

**Goal**: Per-session publish/unpublish behind a re-checking gate, automatic revert-to-draft on edit, and the students' published-only chronological exam table.

**Independent Test**: Per spec US3 — draft invisible to students; published session appears complete; publish blocked on 0 examinees / stale seating / conflicts with report; edit reverts to draft and disappears from student view; per-session mix (published A + draft B) behaves correctly.

### Tests for User Story 3 (write FIRST, must fail) ⚠️

- [x] T030 [P] [US3] Publish-gate tests in `tests/Feature/ExamSchedule/ExamPublishTest.php` — `assertPublishable` refusals (0 examinees, stale, seeded conflict, capacity); success sets `published`; any edit of published session reverts to draft (FR-020); unpublish hides immediately; 403 without `exam_schedules.publish` (R10)
- [x] T031 [P] [US3] Student-view tests in `tests/Feature/ExamSchedule/StudentExamScheduleTest.php` — zero rows pre-publish (SC-005); post-publish full chronological table with venue/committee/seat; unscheduled approved courses show "لم يُحدد بعد"; only the authenticated student's own published exams (student guard)

### Implementation for User Story 3

- [x] T032 [US3] Extend `app/Services/ExamScheduleService.php` — `assertPublishable()` (examinees>0 ∧ ¬stale — staleness incl. over-capacity committees per R9/FR-018 — ∧ zero conflicts ∧ total capacity satisfied), `publish()/unpublish()` inside transaction under the per-year lock with conflict re-check (R5/R10), automatic draft-revert in `update()` paths touching session fields/committees/distribution (FR-020)
- [x] T033 [US3] Extend `app/Livewire/Admin/ExamSchedule/Index.php` + `index.blade.php` — publish/unpublish actions (permission-checked, confirm dialogs, toast reports on gate refusal), status + stale badges surfaced per contracts/admin-ui.md
- [x] T034 [US3] Create `app/Livewire/Student/ExamSchedule.php` + `resources/views/livewire/student/exam-schedule.blade.php`, register the `student.exam-schedule` route inside the existing student-guard group of `routes/web.php` per `contracts/routes.md`, and mount the component as a block on `resources/views/livewire/student/dashboard.blade.php` — published-only table per `contracts/student-view.md`, Arabic weekday from Carbon, print link
- [x] T035 [US3] Run `php artisan test --compact --filter=Exam` until green + `vendor/bin/pint --dirty --format agent`

**Checkpoint**: End-to-end admin→student flow works with the publish safety valve

---

## Phase 6: User Story 4 - Attendance sheets & print (Priority: P4)

**Goal**: Printable per-committee attendance sheets and student exam-table print pages from the same source students see.

**Independent Test**: Per spec US4 — committee sheet lists exactly the placed students with matching seat numbers; student print matches dashboard; regenerate → sheet reflects new state.

### Tests for User Story 4 (write FIRST, must fail) ⚠️

- [x] T036 [P] [US4] Print smoke tests in `tests/Feature/ExamSchedule/ExamPrintTest.php` — committee sheet shows seat/code/name/section for exactly that committee's students and equals their personal-table rows (SC-004); student schedule print renders published table; 403 without `exam_schedules.view` / `students.view`

### Implementation for User Story 4

- [x] T037 [US4] Create `app/Http/Controllers/Admin/ExamPrintController.php` (`committeeSheet()`, `studentSchedule()`) and register the two print routes in `routes/web.php` per `contracts/routes.md`
- [x] T038 [P] [US4] Create print views `resources/views/admin/pages/exam/committee-sheet.blade.php` and `resources/views/admin/pages/exam/student-schedule.blade.php` — standalone `@media print`-friendly Blade cloning `resources/views/admin/pages/student/print_seat_number.blade.php` structure, signature column + "آخر تحديث" footer on sheets (contracts/student-view.md)
- [x] T039 [US4] Run `php artisan test --compact --filter=ExamPrint` until green + `vendor/bin/pint --dirty --format agent`

**Checkpoint**: All four user stories independently functional

---

## Phase 7: Polish & Cross-Cutting Concerns

- [x] T040 [P] Extend `database/seeders/DemoDataSeeder.php` with a full-term exam scenario (multi-course overlapping registrations, committees, published + draft sessions) for manual review
- [ ] T041 Execute the `specs/002-exam-scheduling/quickstart.md` manual walkthrough end-to-end (steps 1–9) and fix any surfaced gaps
- [x] T042 Run full `php artisan test --compact` (no regressions) and final `vendor/bin/pint --dirty --format agent`
- [x] T043 [P] Verify dark-launch safety per plan.md: `migrate --force` + `PermissionsSeeder --force` on a fresh DB, confirm feature invisible until permissions granted and nothing student-visible before first publish (SC-005)
- [x] T044 [P] Deletion-guard tests in `tests/Feature/ExamSchedule/ExamDeletionGuardTest.php` — deleting a venue, year, course, or exam session referenced by existing exam data is rejected with the standard Arabic guard message (FR-023, same pattern as 001's venue/course/section guard tests)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately
- **Foundational (Phase 2)**: Depends on Phase 1 — BLOCKS all user stories (T004–T006 migrations before T007+ models; T012 routes before story components)
- **User Stories (Phases 3–6)**: All depend on Phase 2
  - US1 → independent (MVP)
  - US2 → depends on US1's `ExamScheduleService::examinees()` (distribution audience)
  - US3 → depends on US1 (sessions) + US2 (stale check in publish gate)
  - US4 → depends on US2 (assignments) + US3 (published-only print data)
- **Polish (Phase 7)**: After desired stories complete

### Within Each User Story

- Tests written and FAILING before implementation (constitution V)
- Models/migrations → services → Livewire/routes → green tests + Pint

### Parallel Opportunities

- Phase 1: T001, T002, T003 all parallel
- Phase 2: T004/T005/T006 parallel; T007/T008 parallel after migrations; T010/T011 parallel
- US1 tests T014/T015/T016 parallel; US2 tests T022/T023 parallel; US3 tests T030/T031 parallel
- Sequential story order recommended (US2–US4 build on US1's service); team-splitting only after Phase 2 with US1 done

---

## Parallel Example: User Story 1

```bash
# Write all US1 tests together (they must fail first):
Task: "Service tests in tests/Feature/ExamSchedule/ExamScheduleServiceTest.php"
Task: "Atomic-save test in tests/Feature/ExamSchedule/ExamScheduleAtomicSaveTest.php"
Task: "Permission + form tests in tests/Feature/ExamSchedule/ExamScheduleFormTest.php"

# Then implement service (T017) before the two screens; Index (T018) and Form (T019)
# touch different files and can run in parallel, with sidebar (T020) alongside.
```

---

## Implementation Strategy

### MVP First (User Story 1 only)

1. Phase 1 Setup → 2. Phase 2 Foundational → 3. Phase 3 US1 → 4. **STOP & VALIDATE**: `--filter=ExamSchedule` green + quickstart steps 2–3 manually → demo: conflict-free term schedule exists.

### Incremental Delivery

1. +US2 → distribution & seats (quickstart steps 4–5) — still fully dark (draft only)
2. +US3 → publish gate + student table (quickstart steps 6–7, 9) — first student-visible value
3. +US4 → attendance sheets (quickstart step 8) — exam-day operations
4. Each story adds value without breaking previous ones; nothing reaches students before an explicit publish.

---

## Notes

- [P] = different files, no incomplete dependencies
- No CSV import tasks anywhere — deliberately deferred (research R1); `ExamSeatingService` is the single write seam a future importer must reuse
- Commit after each task or logical group; conventional style per constitution (`feat(exams): …`)
- Avoid: touching `Registration`/wallet code (read-only reliance), adding composer/npm packages, Tailwind restyle

---

## Phase 8: Convergence

**Purpose**: Close gaps found by `/speckit.converge` between the artifacts and the implemented code (appended 2026-09-08; existing tasks untouched)

- [x] T045 Replace the `session()->flash` feedback in `updateExamWindows()` with the toast event bridge (`$this->dispatch('toast', ['message' => 'تم تحديث فترات الامتحانات بنجاح', 'type' => 'success'])`) in `app/Livewire/Admin/YearSettings.php` — new code only, pre-existing actions in the file stay as-is — and assert the dispatched toast in `tests/Feature/ExamSchedule/` per Constitution IV (contradicts) [CRITICAL]
- [x] T046 [P] Add the audience-empty state "لا يوجد ممتحنون" to `resources/views/livewire/admin/exam-schedule/seating.blade.php` when `examineeCount` is 0, per the `Seating` empty-states contract (partial)
- [x] T047 [P] Implement the board `sort` state (name / examinees / exam date / status) in `app/Livewire/Admin/ExamSchedule/Index.php` + `resources/views/livewire/admin/exam-schedule/index.blade.php` per the `Index` component contract (partial)
