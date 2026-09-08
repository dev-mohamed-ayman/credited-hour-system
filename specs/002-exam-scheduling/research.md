# Research: Exam Scheduling, Committees & Seating

**Feature**: 002-exam-scheduling | **Date**: 2026-09-08
**Inputs**: [spec.md](./spec.md) (7 clarifications, 2026-09-08), `Exam Scheduling — Implementation Plan (How).md` (repo-root technical proposal), constitution v1.0.0, delivered 001-lecture-scheduling code (`Venue`, `LectureSchedule`, conflict/locking patterns).

All Technical Context entries in plan.md are resolved — no NEEDS CLARIFICATION remains.

## R1. Phase-1 scope reconciliation with the How document

- **Decision**: Build sessions + committees + automatic distribution + publish gate + student view + print sheets. **Drop from phase 1**: `importFromCsv()` in `ExamSeatingService`, the CSV upload UI in `Seating`, the `exams:import-legacy` console command, and all import tests — deferred per clarification Q3 (2026-09-08). Keep a documented seam: distribution writes go through `ExamSeatingService` only, so a future importer reuses the same validation without schema change.
- **Rationale**: Clarification answer B — automatic generation alone for launch; the spec (FR list, edge cases, SC-006) no longer contains import requirements. The How document predates the clarification.
- **Alternatives considered**: Ship import anyway (How-doc default Q1=A) — rejected by the stakeholder decision; it also carries the encoding/header-tolerance complexity (How-doc R4) with no launch need.

## R2. Conflict scoping and overlap semantics

- **Decision**: Two exam sessions conflict ⇔ same `year_id` AND same `semester` AND same `exam_date` AND `start < other.end && end > other.start`. Back-to-back (11:00|11:00) is allowed. Times stored as `TIME` (`HH:MM`), input granularity 15 minutes (`minute % 15 === 0`), end strictly after start. No cross-semester or cross-year checks (spec edge case; mirrors 001 R2).
- **Rationale**: Spec FR-005/FR-006/FR-008 and the source's BR-3/BR-4; each semester is operationally independent (summer after second). Reuses the proven `overlaps()` minute-math pattern from `LectureSchedule::overlaps()`.
- **Alternatives considered**: Weekly-weekday model like lectures — wrong: exams are dated one-offs, not recurring; datetime columns — rejected in favor of separate DATE+TIME so same-day overlap math stays index-friendly and mirrors the How-doc sketch.

## R3. Examinee audience (single source of truth)

- **Decision**: `ExamScheduleService::examinees(ExamSession $session)` returns students via one query: `registrations` with `year_id = session.year`, `semester = session.semester`, `status = approved`, joined through `registration_courses` on `course_id = session.course_id`, over non-soft-deleted students. No other source is recognized (spec FR-004).
- **Rationale**: BR-2 of the source proposal — this is exactly the structure the legacy system lacked; `Registration`+`RegistrationCourse` already answer "who sits this course" precisely, including level/department/section via the student's own relations.
- **Alternatives considered**: Snapshotting the audience at distribution time — rejected: the stale-flag rule (FR-017) requires comparing live audience vs. assignments anyway; a snapshot would drift silently.

## R4. Student-conflict detection without N+1

- **Decision**: `findStudentConflicts(ExamSession $session)` is ONE query: students in `examinees($session)` who also appear in `examinees()` of any other same-year+semester session with an overlapping date/time — implemented as a join across `exam_seat`-free tables: `registration_courses` (this course) ⋈ `registrations` (approved, same term) ⋈ `registration_courses` (other course) ⋈ `exam_sessions` (overlapping window), selecting student id + both course ids. Returns typed `StudentConflict` value objects (student, session A, session B).
- **Rationale**: How-doc §6 requirement ("query واحد بدون N+1") and risk R1 (thousands of students × dozens of courses). The composite index `exam_sessions[year_id, semester, exam_date]` plus `registration_courses[course_id]` keeps it a set operation, not a loop.
- **Alternatives considered**: Per-student checks in PHP — O(students × sessions), unacceptable at scale; materializing a conflict table — extra consistency burden for a derived fact.

## R5. Atomic save-time enforcement (absolute guarantee)

- **Decision**: `ExamScheduleService::create()/update()` run inside `DB::transaction` and take `lockForUpdate()` on the **`years` row** of the session (single serialization point per academic year) **before** running uniqueness/conflict/venue/window checks, then persist. The unique composite index `(course_id, year_id, semester, type)` is the final DB-level backstop against duplicate sessions. The later of two racing submissions fails with the normal Arabic conflict message.
- **Rationale**: Clarification Q3 (2026-09-08): absolute guarantee. Student conflicts span arbitrary course pairs, so per-venue/per-row locks (the 001 pattern) don't cover them — a coarse per-year lock does, and exam writes are rare (a handful of admins preparing a term). Portable: MySQL row locks; sqlite tests serialize transactions anyway.
- **Alternatives considered**: (B) lock venue rows + course rows only — misses student-transitive conflicts; (C) MySQL `GET_LOCK()` advisory names — extra moving part, same effect, less portable to sqlite tests; (D) best-effort check — explicitly rejected by the clarification.

## R6. Exam window storage (optional guard)

- **Decision**: Add six nullable `DATE` columns to `years`: `first_semester_exam_from/to`, `second_semester_exam_from/to`, `summer_semester_exam_from/to` (additive migration, complete `down()`). Validation (FR-003): when both dates of the session's semester are configured, `exam_date` MUST fall inside; when unset, any date is accepted. Configuring a window later does not retroactively invalidate saved sessions — enforcement is at save time for new/edited sessions only. Edited in the Year Form screen (`YearSettings`) under a new "فترات الامتحانات" section.
- **Rationale**: Clarifications Q4 (yes, configurable) + Q4-session-2 (optional guard). One window per semester is all the spec requires, so columns on `years` beat a separate table (How-doc §3.4 offered both).
- **Alternatives considered**: `exam_periods` table — justified only for multiple windows per term, which the spec excludes; a global setting — cannot express per-semester windows.

## R7. Distribution generation: incremental, placement-preserving

- **Decision**: `ExamSeatingService::generateDistribution(ExamSession $session)`:
  1. Delete assignments for students **no longer in the audience** (rejected/cancelled/withdrawn).
  2. Keep every existing assignment untouched — including manual moves (`moveStudent()` places a student into a chosen committee at the next free seat number; the assignment is ordinary data, nothing marks it "manual" — it simply persists).
  3. Place **unplaced** audience members in stable Arabic-name order into committees in defined order, filling each to capacity; seat number = next unused number within the committee (max existing + 1). Existing students keep their seat numbers permanently — gaps left by removed students are allowed and never renumbered (stability beats contiguity; renumbering survivors would contradict "keeps existing placements" and break SC-006 trust).
  4. If unplaced students remain because total capacity < audience size → throw `ExamScheduleException` with the exact Arabic message "عدد الممتحنين (X) يتجاوز إجمالي سعة اللجان (Y)" and roll back (transaction).
- **Rationale**: Clarification Q2 (2026-09-08) answer B — regeneration keeps placements, removes ineligible, absorbs newcomers; FR-015 idempotency = same audience + same placement state ⇒ same result. The How-doc's "delete old assignments + chunk" (full reset) is superseded.
- **Alternatives considered**: (A) full reset — destroys admin corrections on every new registration, rejected by clarification; (C) prompt reset-vs-incremental each time — extra decision fatigue, deferred until a real need appears.

## R8. Seat-number format seam

- **Decision**: Seat numbers are sequential within a committee, stored as string, unique per `(exam_committee_id, seat_number)` at DB level. Generation funnels through one private formatter in `ExamSeatingService` so the legacy "5 digits starting with level number" rule (old `seating_numbers`) can be reintroduced later as a config-driven format without schema change. Not enforced in this phase (spec FR-014, assumption Q2).
- **Rationale**: Single seam keeps the format decision reversible; string storage keeps both schemes expressible.
- **Alternatives considered**: Enforcing the legacy rule now — rejected: it collides with the new section-based structure (How-doc R3 flagged the ambiguity).

## R9. "Distribution out of date" is derived, not persisted

- **Decision**: `isSeatingStale(ExamSession $session): bool` returns true when the live audience set differs from the assignment set (count + id diff in one query) **OR** any committee's `assignedCount()` exceeds its `capacity()` (FR-018 over-capacity condition). No `is_stale` column. The badge on `Index`/`Seating` and the publish gate (`assertPublishable()`) both call it.
- **Rationale**: A persisted flag can drift (registrations change from many code paths — approval, rejection, transfer); derivation is always correct and cheap at this scale. Spec FR-017 defines the behavior, not storage.
- **Alternatives considered**: Observer-set flag on `Registration` approval — scatters exam logic into registration code, violates thin-service principle.

## R10. Publish gate & per-session lifecycle

- **Decision**: `ExamSessionStatus`: `Draft` (مسودة) / `Published` (منشور). `publish()` runs inside a transaction under the same per-year lock: re-check conflicts (R4/R5), `assertPublishable()` = examinees > 0 ∧ not stale ∧ zero student/venue conflicts ∧ capacity satisfied; then set status. `unpublish()` returns to draft immediately. Any `update()` of a published session (time, date, committees, distribution edits) reverts status to draft automatically (FR-020). Students see only `Published` sessions of their approved registrations for the selected year+semester.
- **Rationale**: Clarification Q1 (2026-09-08): per-session publish; a term may mix published and draft sessions (late resits). Re-checking at publish closes the "conflict appeared after save" window (spec edge case).
- **Alternatives considered**: Term-level publish button — rejected by clarification; scheduled publishing — out of scope.

## R11. Deletion guards & FK policy

- **Decision**: Model guards via `HasDeletionGuards`: `ExamSession::$blockingRelations = ['committees', 'seatAssignments']`; append `examSessions` to `Course` and `Year` blocking lists; append `examCommittees` to `Venue`. DB backstops: `course_id`/`exam_session_id` FKs `cascadeOnDelete` (owned rows); `venue_id` and `year_id` FKs `restrictOnDelete` (referenced academic data — deviation from the How-doc's `cascadeOnDelete` on `year_id`, aligned with the constitution FK rule and the delivered `lecture_schedules` precedent where year is nullable+nullOnDelete; here `year_id` is NOT NULL because exam scoping requires it, so restrict is the correct guard).
- **Rationale**: Spec FR-023; deleting a year/venue with exam history must be blocked at the UI, and the DB must not silently orphan conflict scoping.
- **Alternatives considered**: `nullOnDelete` on `year_id` like lectures — would leave year-less exam sessions that no conflict scope can place; cascade on `venue_id` — would let venue deletion erase committees, violating FR-023.

## R12. Venues dependency (How-doc Q6 / risk R2)

- **Decision**: Reuse the delivered `venues` table and `Venue` model from 001-lecture-scheduling as-is (it exists in `database/migrations/2026_09_08_000001_create_venues_table.php`). No shared-migration coordination needed; committee venue pickers list active venues only. Venue-conflict checks (FR-006) consider exam sessions only — lecture bookings do not block exams (different tables, different quality; cross-resource conflict with lectures is out of scope for both features).
- **Rationale**: The dependency resolved itself — 001 shipped first, eliminating How-doc risk R2.
- **Alternatives considered**: Including a guarded venues migration here — unnecessary duplication now.

## R13. Student surface

- **Decision**: `App\Livewire\Student\ExamSchedule` — full-page component on the `student` guard mounted as a block on the student dashboard (FR-021 "on their dashboard"), showing published sessions of the student's approved registrations for the current year+semester, ordered by date+time, with Arabic weekday derived from Carbon; courses without a scheduled exam show "لم يُحدد بعد" (useful legacy behavior from `getStudentExamTable`). Print link → `ExamPrintController::studentSchedule()` (browser print, `print_seat_number.blade.php` structure).
- **Rationale**: Mirrors the legacy student home page idea from one validated source; a block (not a separate page) matches the spec's dashboard wording; print needs a real route (Livewire can't render standalone print docs cleanly).
- **Alternatives considered**: Separate student page only — fails "on their dashboard"; embedding print in Livewire — fights the existing print-page pattern.
