---
description: "Task list for Lecture & Venue Scheduling implementation"
---

# Tasks: Lecture & Venue Scheduling

**Input**: Design documents from `/specs/001-lecture-scheduling/`

**Prerequisites**: plan.md (required), spec.md (required for user stories), research.md, data-model.md, contracts/, quickstart.md

**Tests**: INCLUDED — the constitution (Principle V) and spec success criteria mandate Pest coverage of every business rule before UI; service tests are written first and must fail before implementation.

**Organization**: Tasks grouped by user story (US1 P1, US2 P2, US3 P3 from spec.md) so each story is independently implementable and testable.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: US1 = Conflict-free session scheduling, US2 = Venue management, US3 = Weekly schedule review
- Exact file paths included per task

## Phase 1: Setup (Schema & Enums)

**Purpose**: Additive database tables and enum vocabulary everything else depends on. No new packages, no existing table altered (plan.md Constraints).

- [ ] T001 [P] Create venues migration `database/migrations/2026_09_08_000001_create_venues_table.php` — `name` string unique, `type` string, `capacity` unsignedInteger nullable, `is_active` boolean default true, `notes` text nullable, `timestamps()`; complete `down()` (data-model.md §1)
- [ ] T002 [P] Create lecture_schedules migration `database/migrations/2026_09_08_000002_create_lecture_schedules_table.php` — `course_id` FK cascadeOnDelete, `venue_id` FK restrictOnDelete, `year_id` FK nullable nullOnDelete, `day` string, `start_time`/`end_time` time, `timestamps()`; composite indexes `['venue_id','day','start_time','end_time']` and `['course_id','day']`; explicit `dropForeign` + `dropColumn` in `down()` (data-model.md §2)
- [ ] T003 [P] Create pivot migration `database/migrations/2026_09_08_000003_create_lecture_schedule_section_table.php` — `lecture_schedule_id` + `section_id` FKs cascadeOnDelete, `unique(['lecture_schedule_id','section_id'])` (data-model.md §3)
- [ ] T004 [P] Create backed enum `App\Enums\VenueType` in `app/Enums/VenueType.php` — cases Auditorium/Lab/Classroom/Other (values `auditorium|lab|classroom|other`) with Arabic `label()` (مدرج/معمل/قاعة دراسية/أخرى), mirroring `app/Enums/Semester.php` style (research R9)
- [ ] T005 [P] Create backed enum `App\Enums\DayOfWeek` in `app/Enums/DayOfWeek.php` — Saturday..Thursday only (no Friday case, research R9) with Arabic `label()` and `order(): int` for grid sorting
- [ ] T006 Run `php artisan migrate --force` and confirm the three tables exist with indexes

**Checkpoint**: Schema + enums ready; models can be built.

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Shared models, factories, permissions, exception — MUST complete before any user story.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [ ] T007 Create `App\Models\Venue` in `app/Models/Venue.php` — fillable `name,type,capacity,is_active,notes`, `casts()` method (`type => VenueType`, `is_active => boolean`), `lectureSchedules(): HasMany`, `use HasDeletionGuards` with `$blockingRelations = ['lectureSchedules']` (data-model.md §1)
- [ ] T008 Create `App\Models\LectureSchedule` in `app/Models/LectureSchedule.php` — fillable `course_id,venue_id,year_id,day,start_time,end_time`, `casts()` (`day => DayOfWeek`), relations `course()/venue()/year()/sections(): BelongsToMany('lecture_schedule_section')`, helpers `durationInMinutes(): int`, `overlaps(self $other): bool` (same day && `start < other.end && end > other.start`, research R3); NO student-count helper on the model — counting lives solely in `LectureScheduleService::selectedStudentsCount()` (research R1) so the Form preview and the server check share one implementation
- [ ] T009 [P] Add `lectureSchedules(): HasMany` and append `'lectureSchedules'` to `$blockingRelations` in `app/Models/Course.php` (data-model.md Modified Models)
- [ ] T010 [P] Add `lectureSchedules(): BelongsToMany` and append `'lectureSchedules'` to `$blockingRelations` in `app/Models/Section.php`
- [ ] T011 [P] Create `database/factories/VenueFactory.php` — states `auditorium()`, `lab()`; unique name sequence
- [ ] T012 [P] Create `database/factories/LectureScheduleFactory.php` — times on 15-minute boundaries, sequence-safe day/time defaults
- [ ] T013 Add `venues` (label "الأماكن والمدرجات") and `lecture_schedules` (label "جدول المحاضرات") modules with view/create/edit/delete Arabic action labels to `config/permissions.php` (contracts/routes.md Permissions table)
- [ ] T014 Run `php artisan db:seed --class=PermissionsSeeder --force` (idempotent) and verify the 8 new permissions exist
- [ ] T015 Create `App\Exceptions\LectureScheduleConflictException` in `app/Exceptions/LectureScheduleConflictException.php` — typed exception carrying a ready Arabic message (constitution I pattern: typed domain exceptions, never generic strings)

**Checkpoint**: Foundation ready — user stories can now proceed (US1 and US2 in parallel; US3 needs US1's Index).

## Phase 3: User Story 1 - Conflict-free lecture session scheduling (Priority: P1) 🎯 MVP

**Goal**: Admins attach lecture sessions (venue + day + time + sections via checkboxes/range) to courses with server-enforced capacity, venue-conflict, section-conflict, membership, and concurrency rules; reactive create/edit screen.

**Independent Test**: Pre-provision venues + a course with sections (factories), then create a valid session (DB + pivot rows) and attempt each rejection: venue overlap, section overlap, capacity overrun, foreign section — each rejected with Arabic message and no DB row (spec US1 acceptance scenarios 1–6).

### Tests for User Story 1 (write FIRST — must fail before T020) ⚠️

- [ ] T016 [P] [US1] Create validation test suite in `tests/Feature/LectureSchedule/LectureScheduleValidationTest.php` — capacity overrun rejected with "الإجمالي المختار N طالب يتجاوز سعة ... (M)"; `capacity=null` skipped; zero-enrollment section counted via `RegistrationFee.number_of_students_per_section` fallback (research R1); sections not on `course_section` pivot rejected server-side (FR-005); `ignoreScheduleId` lets a session edit without self-conflict (FR-013); time rules: `end_time` must be after `start_time` and both on 15-minute boundaries — invalid times rejected (FR-010)
- [ ] T017 [P] [US1] Create conflict test suite in `tests/Feature/LectureSchedule/LectureScheduleConflictTest.php` — venue overlap rejected / 10:30→10:30 adjacency accepted / different days accepted (research R3); section clash across two different courses rejected (FR-009); same hall/section in a DIFFERENT year or semester NOT a conflict (research R2); create stamps `year_id` = `Year::current()->id`, and a session created with no active Year persists with null `year_id` scoping only against null-year sessions (FR-012); same section in two sessions of the same course at different days/times is accepted (FR-003); conflict message names course + venue/section + day + time (FR-016)
- [ ] T018 [US1] Create concurrency test in `tests/Feature/LectureSchedule/LectureScheduleConcurrencyTest.php` — prove FR-020 deterministically without claiming real parallelism (sqlite in-memory cannot reproduce an OS-level race; comment this limitation): (a) sequential double-submit — second overlapping save rejected, exactly one row persists; (b) interleaving proof — either run two `DB::transaction` service calls with a barrier hook so both enter validation and only one commits, or assert via `DB::listen` that `lockForUpdate` SQL on the venue + selected section rows is issued BEFORE the conflict queries (research R4)

### Implementation for User Story 1

- [ ] T019 [US1] Implement `App\Services\LectureScheduleService` in `app/Services/LectureScheduleService.php` — `assertSectionsBelongToCourse()`, `assertVenueCapacity()` (actual enrollment, configured fallback when zero), `assertNoVenueConflict()`, `assertNoSectionConflict()` (both scoped to same `year_id` + course semester via `whereHas('course')`, both accept `?int $ignoreScheduleId`, single indexed query per check with `with(['course','venue'])` for message building — no N+1), `validate()` orchestrator throwing `LectureScheduleConflictException`, `selectedStudentsCount(array $sectionIds): int` as the single counting implementation (actual enrollment + R1 fallback) shared by preview and server check, `create()` stamps `year_id` from `Year::current()?->id`, and `create()`/`update()` wrap validation + persistence in `DB::transaction` with `lockForUpdate()` on venue + section rows (research R4/R5); makes T016–T018 pass
- [ ] T020 [US1] Create full-page Livewire component `App\Livewire\Admin\LectureSchedule\Form` in `app/Livewire/Admin/LectureSchedule/Form.php` + `resources/views/livewire/admin/lecture-schedule/form.blade.php` — properties `venue_id,day,start_time,end_time,section_ids[],range_from,range_to`; create mode via `{course}`, edit via `{schedule}`; venue select = active venues only for new sessions, current venue kept selectable on edit (research R10); sections from `$course->sections()` with `withCount('students')`; `applyRange()` normalizes reversed ranges and dedupes merges (research R6); live total via `LectureScheduleService::selectedStudentsCount()` (same method the server check uses — no drift, R1) vs `venue.capacity` progress (red on overrun, preview only), showing "سعة غير محددة" in place of the bar when the venue has no capacity; `save()` = `abort_unless(can(create|edit),403)` → in-component `rules()` (end>start, 15-min boundary `minute % 15 === 0`, day ∈ DayOfWeek, section_ids non-empty) → service call → catch conflict → `addError`/danger toast → success redirect + toast (contracts/scheduling-ui.md Form)
- [ ] T021 [US1] Create `App\Livewire\Admin\LectureSchedule\Index` in `app/Livewire/Admin/LectureSchedule/Index.php` + `resources/views/livewire/admin/lecture-schedule/index.blade.php` — Department→Level→Semester→course-list filters (same pattern as `app/Livewire/Admin/Course/Index.php`); selected course shows session table (day label, times, venue, sections, Σ students vs capacity) with Edit/Delete buttons + "إضافة جلسة"; `delete()` action with `abort_unless(can('lecture_schedules.delete'),403)` + `window.confirmAction()` + toast (contracts/scheduling-ui.md Index)
- [ ] T022 [US1] Register routes in `routes/web.php` admin group: `GET lecture-schedules` → Index (`permission:lecture_schedules.view`), `GET lecture-schedules/{course}/create` + `GET lecture-schedules/{schedule}/edit` → Form (create/edit permissions) per contracts/routes.md
- [ ] T023 [US1] Add "جدول المحاضرات" link under the "المواد الدراسية" sidebar group in `resources/views/admin/layouts/sidebar.blade.php` wrapped in `@can('lecture_schedules.view')`
- [ ] T024 [US1] Create Livewire feature tests in `tests/Feature/LectureSchedule/LectureScheduleFormTest.php` — guest & permission-less user → 403 on all three routes and on `save()`/`delete()` actions; valid create → `assertDatabaseHas` schedule + pivot rows + success toast; conflict attempt → Arabic message + zero new rows; range "1→10" produces correct IDs and "10→1" normalizes; day input cannot yield Friday (no enum case, FR-011); computed live total updates with `section_ids` changes (FR-017); invalid times (end ≤ start, off-quarter-hour) produce Arabic validation errors (FR-010); `Livewire::actingAs($user,'web')` per constitution V

**Checkpoint**: US1 fully functional — admin can schedule conflict-free sessions end-to-end (MVP).

## Phase 4: User Story 2 - Venue management (Priority: P2)

**Goal**: Flat CRUD for venues (unique name, type, optional capacity, active status) with deletion guards, mirroring the sections pages.

**Independent Test**: Create/edit/deactivate venues; duplicate name rejected; deleting a venue referenced by sessions blocked with guard message (spec US2 acceptance scenarios 1–4).

### Tests for User Story 2 ⚠️

- [ ] T025 [P] [US2] Create venue CRUD tests in `tests/Feature/LectureSchedule/VenueCrudTest.php` — store/update happy paths + unique-name rejection with Arabic message; per-action 403s for missing `venues.*` permissions; destroy of referenced venue blocked (message contains count, row remains); destroy of unreferenced venue succeeds; inactive venue absent from Form's new-session select but present when editing its own session (research R10)

### Implementation for User Story 2

- [ ] T026 [P] [US2] Create `App\Http\Requests\Admin\StoreVenueRequest` in `app/Http/Requests/Admin/StoreVenueRequest.php` — rules `name required|string|max:255|unique:venues,name`, `type required|in:` from `VenueType::cases()`, `capacity nullable|integer|min:1`, `is_active nullable|boolean`, `notes nullable|string|max:1000`; Arabic `messages()` mirroring `StoreSectionRequest` (contracts/venue-crud.md)
- [ ] T027 [P] [US2] Create `App\Http\Requests\Admin\UpdateVenueRequest` in `app/Http/Requests/Admin/UpdateVenueRequest.php` — same rules with `unique:venues,name,{id}` except-self
- [ ] T028 [US2] Implement `App\Http\Controllers\Admin\VenueController` in `app/Http/Controllers/Admin/VenueController.php` — resource `except(['show'])` cloning `SectionController` structure; destroy checks `hasBlockingRelations()` → block with `getBlockingRelationsMessage()`, else delete; toasts on success (contracts/venue-crud.md)
- [ ] T029 [P] [US2] Create views `resources/views/admin/pages/venue/{index,create,edit}.blade.php` — clone `resources/views/admin/pages/section/*` structure; index shows type `label()`, capacity or "غير محددة", active badge via enum; `@can` on buttons
- [ ] T030 [US2] Register `Route::resource('venues', VenueController::class)->except(['show'])->middleware('permission:venues.view')` in `routes/web.php` admin group (contracts/routes.md)
- [ ] T031 [US2] Add "الأماكن والمدرجات" link under the "المواد الدراسية" sidebar group in `resources/views/admin/layouts/sidebar.blade.php` wrapped in `@can('venues.view')`
- [ ] T032 [US2] Run T025 suite green (`php artisan test --compact --filter=VenueCrud`)

**Checkpoint**: US1 + US2 both independently functional.

## Phase 5: User Story 3 - Weekly schedule review (Priority: P3)

**Goal**: Course-centric weekly day × time grid plus render-time review flags (over-capacity, orphan sections) on the Index screen.

**Independent Test**: With sessions saved across days, WeekGrid shows each in its correct cell; lowering venue capacity below attendance flags "تجاوز السعة" without deleting; unlinked section shows warning and is blocked from new sessions (spec US3 scenarios 1–2).

### Tests for User Story 3 ⚠️

- [ ] T033 [P] [US3] Create review tests in `tests/Feature/LectureSchedule/WeekGridTest.php` — grid renders both weekly sessions in correct day/time cells ordered by `DayOfWeek::order()`; over-capacity badge appears after venue capacity lowered (no row deleted); orphan-section warning appears after pivot unlink and that section is rejected by the service for new sessions (FR-019, research R11)

### Implementation for User Story 3

- [ ] T034 [US3] Create `App\Livewire\Admin\LectureSchedule\WeekGrid` in `app/Livewire/Admin/LectureSchedule/WeekGrid.php` + `resources/views/livewire/admin/lecture-schedule/week-grid.blade.php` — day columns Saturday..Thursday via `order()`, time rows; one eager query (`with(['venue','sections'])`) scoped to course + year/semester; venue-centered view explicitly out of scope (FR-018)
- [ ] T035 [US3] Register `GET lecture-schedules/{course}/grid` → WeekGrid (`permission:lecture_schedules.view`) in `routes/web.php` and link it from the Index course header in `resources/views/livewire/admin/lecture-schedule/index.blade.php`
- [ ] T036 [US3] Add computed review flags to `App\Livewire\Admin\LectureSchedule\Index.php` + `index.blade.php` — per-session "تجاوز السعة" badge (current Σ vs venue capacity) and "قسم غير مرتبط" warning (section no longer on `course_section`), both render-time, never stored (research R11)

**Checkpoint**: All three stories independently functional.

## Phase 6: Polish & Cross-Cutting Concerns

- [ ] T037 [P] Append venues + conflict-free demo sessions block to `database/seeders/DemoDataSeeder.php` for the demo environment (data-model.md Factories & Seeders)
- [ ] T038 Run full suite `php artisan test --compact` — zero failures, no regressions to money/registration flows (constitution V)
- [ ] T039 Run `vendor/bin/pint --dirty --format agent` and fix any style output
- [ ] T040 Execute `specs/001-lecture-scheduling/quickstart.md` manual scenarios 1–4 in the browser (run `npm run build` first if views/assets changed) and confirm SC-001/SC-003/SC-004 spot checks

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately
- **Foundational (Phase 2)**: Requires Phase 1 — BLOCKS all user stories
- **User Stories (Phases 3–5)**: All require Phase 2
  - US1 and US2 can proceed **in parallel** after Phase 2 (different files except sidebar/routes — sequence T022/T023 vs T030/T031 or merge edits)
  - US3 depends on US1's `Index` component existing (T021) — deliver after US1
- **Polish (Phase 6)**: After all desired stories

### User Story Dependencies

- **US1 (P1)**: After Phase 2 — independent (needs Venue model/rows only, provisioned by factories)
- **US2 (P2)**: After Phase 2 — independent of US1; its deletion-guard test uses `LectureScheduleFactory` from Phase 2
- **US3 (P3)**: After Phase 2 + US1 T021 (extends Index view/component)

### Within Each User Story

- Tests written and FAILING before implementation (T016–T018 → T019; T025 → T026–T032; T033 → T034–T036)
- Models (Phase 2) → Service → Components → Routes/Sidebar → Feature tests
- Story complete before next priority

### Parallel Opportunities

- T001–T005 (Setup: five distinct files)
- T009–T012 (Foundational: distinct files)
- T016–T018 (three distinct test files)
- US1 ∥ US2 after Phase 2 (two developers), coordinating `routes/web.php` + `sidebar.blade.php` edits (shared files — not [P] with each other)
- T026/T027, T029, T025 (US2 distinct files)

---

## Parallel Example: User Story 1

```bash
# Write all service tests together (they must fail):
Task: "T016 Validation tests in tests/Feature/LectureSchedule/LectureScheduleValidationTest.php"
Task: "T017 Conflict tests in tests/Feature/LectureSchedule/LectureScheduleConflictTest.php"

# Then implement the service once (T019), and build UI pieces in parallel:
Task: "T020 Form component + view"
Task: "T021 Index component + view"
```

## Parallel Example: User Stories 1 ∥ 2

```bash
Developer A: T016–T024 (scheduling service + Livewire)
Developer B: T025–T032 (venue CRUD) — after Phase 2, touching disjoint files
             (hand-edit routes/web.php + sidebar.blade.php after both merge, or sequence T022→T030)
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Phase 1 Setup → 2. Phase 2 Foundational → 3. Phase 3 US1 (tests first!) → 4. **STOP & VALIDATE**: `php artisan test --compact --filter=LectureSchedule` + quickstart scenario 1 → 5. Deploy (additive, zero downtime — research R13)

### Incremental Delivery

1. Setup + Foundational → checkpoint
2. US1 → independently tested → **MVP deployed** (conflict-free scheduling live; venues seeded directly if needed)
3. US2 → venue self-service CRUD → deploy
4. US3 → review grid/flags → deploy
5. Each story adds value without breaking previous ones

### Parallel Team Strategy

1. Together: Phases 1–2
2. Split: Dev A US1, Dev B US2 (coordinate the two shared files)
3. Dev A: US3 (needs US1's Index)
4. Together: Phase 6

---

## Notes

- [P] = different files, no incomplete dependencies; `routes/web.php` and `sidebar.blade.php` are shared — tasks touching them are deliberately NOT [P] across stories
- All UI copy, validation messages, and exception messages are Arabic (constitution IV); views clone Sneat/Bootstrap structures — no Tailwind restyle
- No new composer/npm packages anywhere (plan.md Technical Context)
- Commit after each task or logical group: `feat(scheduling): …` per repo convention
- Stop at any checkpoint to validate the story independently
- Legacy `chs` data migration is explicitly out of scope (research R12)
