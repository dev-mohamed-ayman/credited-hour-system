# Contract: Lecture Schedule Livewire UI

**Feature**: Lecture & Venue Scheduling | Full-page components in `App\Livewire\Admin\LectureSchedule\`, views in `resources/views/livewire/admin/lecture-schedule/`, each `->extends('admin.layouts.app')->section('content')`. All business rules delegate to `LectureScheduleService` (constitution II); components only orchestrate state, validation, authorization.

## `Index` — course browser + session list + review

**Filters** (cascade, same pattern as `Course\Index`): Department → Level → Semester → course list.

**On course selected**, show:
- Session table: day (label), start–end, venue, sections (count/range), Σ students vs venue capacity.
- Per-row Edit (`@can('lecture_schedules.edit')`) / Delete (`@can('lecture_schedules.delete')`) + "إضافة جلسة" (`@can('lecture_schedules.create')`).
- **Review flags** (computed at render, research R11): "تجاوز السعة" badge when venue capacity < current Σ; "قسم غير مرتبط" warning when a session's section was unlinked/reassigned.
- Link to `WeekGrid` for the course.

**Actions**: `delete(LectureSchedule $s)` → `abort_unless(auth()->user()->can('lecture_schedules.delete'), 403)` → confirm via `window.confirmAction()` → delete → toast.

## `Form` — create/edit a session

**Mode** from route: `{course}` = create; `{schedule}` = edit (course derived from schedule).

**Public properties**: `venue_id`, `day`, `start_time`, `end_time`, `section_ids[]`, `range_from`, `range_to`.

**Reactive surface** (the reason this is Livewire — FR-017):
- Available sections = `$course->sections()` with `withCount('students')` (eager, no N+1); each row shows a checkbox + real student count.
- `applyRange()` (from `range_from`/`range_to`): selects the contiguous slice of the course's ordered sections; reversed range normalized to min/max (research R6); merges with checkbox picks, deduplicated.
- `selectedStudentsCount` computed live via `LectureScheduleService::selectedStudentsCount()` — the same method the server check uses, so preview and authoritative count never drift: Σ actual enrollment, configured fallback when a section has 0 (research R1). Rendered as progress vs `venue.capacity`, turning red on overrun; shows "سعة غير محددة" in place of the bar when the venue has no capacity — **preview only**; the authoritative check is server-side on save.
- Venue select lists **active** venues only for new sessions; on edit, the session's current venue stays selectable even if inactive (research R10).

**`save()`**:
1. `abort_unless($user->can('lecture_schedules.create'|'edit'), 403)` (never UI-only hiding).
2. `$this->validate()` — `end_time > start_time`, both on 15-min boundary, day ∈ `DayOfWeek`, `section_ids` non-empty (FR-010/011).
3. Normalize + dedupe `section_ids`.
4. Call `LectureScheduleService::create()`/`update()` (which re-checks membership + capacity + conflicts inside a transaction, research R4/R5).
5. Catch `LectureScheduleConflictException` → `addError`/`dispatch('toast', type:'danger')` with the Arabic message (FR-016).
6. Success → redirect to `Index` for the course + success toast.

**`Form::rules()`** live in-component (constitution II overrides generic Form-Request guidance for Livewire).

## `WeekGrid` — day × time grid (course-centric)

- Rows = time slots, columns = Saturday..Thursday (ordered via `DayOfWeek::order()`); each session rendered in its day/time cell with venue + section range.
- Data loaded via one eager query (`with(['venue','sections'])`) scoped to the course's year+semester; no per-cell queries.
- Venue-centered grid is **out of scope for v1** (FR-018) — the component accepts a course context only.

## Authorization & feedback invariants

- Every mutating action re-checks permission server-side (`abort_unless`), independent of route middleware and `@can` visibility.
- Feedback is toast-only via the existing event bridge; destructive actions confirm via `window.confirmAction()` (constitution IV). No `alert()`/`confirm()`/flash-only.
