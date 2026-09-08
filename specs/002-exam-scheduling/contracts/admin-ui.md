# Contract: Admin Livewire Components

**Feature**: Exam Scheduling, Committees & Seating | Components follow constitution II (thin orchestration; all rules in `ExamScheduleService` / `ExamSeatingService`) and IV (Arabic copy, toast bridge, enum badges). Naming mirrors: `App\Livewire\Admin\ExamSchedule\{Index,Form,Seating}` ↔ `livewire/admin/exam-schedule/{index,form,seating}`.

## `Index` — term board (FR-001)

**State**: `year_id` (default `Year::current()`), `semester` (default `Year::currentSemester()`), `department_id`, `level_id`, `status_filter` (all / not-set / draft / published), `sort`, pagination.

**Data**: paginated rows = courses with ≥1 approved registration in the selected year+semester (R3), each with: course name, department, level, examinee count, session summary (date/time/type or "لم تُحدد"), status badge (`ExamSessionStatus::badgeClass()`), stale-distribution badge (R9).

**Actions** (each `abort_unless(...->can(...))` first):

| Action | Permission | Behavior |
|--------|------------|----------|
| `open(course)` / `edit(session)` / `seating(session)` | view/create/edit | navigate to Form / Seating |
| `delete(session)` | delete | `window.confirmAction()` → guard-checked delete; committees/assignments cascade; published session warns explicitly |
| `publish(session)` | publish | service `publish()` under lock; on `ExamScheduleException` → error toast with the Arabic conflict/stale/capacity report; success toast "تم نشر جدول الامتحانات" |
| `unpublish(session)` | publish | service `unpublish()` → draft immediately |

**Empty state**: no approved registrations in term ⇒ "لا توجد مواد مسجّل بها في هذا الترم".

## `Form` — session + committees (FR-002..FR-011)

**State**: `course_id` (locked on edit), `type` (ExamType select), `exam_date`, `start_time`, `end_time` (15-min steppers), `notes`, `committees[]` repeater: `{venue_id, name, capacity}` with add/remove rows.

**Reactivity** (`wire:model.live`): live counter card — examinees (from service) vs Σ committee capacity, colored over/under; committee name/capacity validation per row; venue select lists active venues only (R12).

**`rules()` highlights**: date required + inside configured exam window (server re-check in service, R6); `end_time > start_time`; ≥1 committee when saving committees; capacity ≥ 1; committee names unique within the session.

**`save()`**: build candidate session → `ExamScheduleService::create()/update()` (atomic, per-year lock, R5). On typed exception → error toast with the full Arabic report (affected students + courses, or conflicting session + venue + times). On success → toast + redirect to `Index`. Editing a published session shows a confirm warning: the session will return to draft (FR-020).

## `Seating` — distribution (FR-012..FR-018)

**State**: `session` (route-bound), tab per committee, per-committee paginated assignment table (seat number / student code / name / section), `moveTarget` selects.

**Header badges**: examinee count, total capacity, status, stale "توزيع غير محدّث" badge (R9) with inline "توليد التوزيع" CTA, and an "over capacity" badge on any committee whose placed students exceed its capacity (FR-018 — staleness includes this condition; publish stays blocked until regeneration).

**Actions**:

| Action | Permission | Behavior |
|--------|------------|----------|
| `generate()` | edit | `ExamSeatingService::generateDistribution()` (incremental, R7); success toast "تم توليد التوزيع"; overflow → error toast "عدد الممتحنين (X) يتجاوز إجمالي سعة اللجان (Y)" and nothing changes (transaction rollback) |
| `move(assignment, committee)` | edit | `moveStudent()` — capacity-checked; re-renders both committees |
| `publish()` / `unpublish()` | publish | same gate as Index (re-checks everything, R10) |

**No CSV upload in phase 1** (R1).

**Empty states**: no committees yet ⇒ CTA to Form; audience empty ⇒ "لا يوجد ممتحنون" + publish blocked.
