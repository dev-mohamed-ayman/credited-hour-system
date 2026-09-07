# Phase 0 Research: Lecture & Venue Scheduling

**Feature**: `specs/001-lecture-scheduling` | **Date**: 2026-09-08
**Inputs**: spec.md (0 remaining NEEDS CLARIFICATION — all resolved via Clarifications Session 2026-09-08), constitution v1.0.0, repo audit in `Lecture Scheduling — Implementation Plan (How).md`.

## R1. Capacity source per section

- **Decision**: Count **actual enrolled students** per section (`Section::students()` — soft-deleted students excluded by the existing global scope). When a section has zero enrollments, fall back to `RegistrationFee.number_of_students_per_section` for the course's department + level. Venue with `capacity = null` skips the check with a "سعة غير محددة" notice.
- **Rationale**: Clarification Q1 answer (A). Real rosters are the truthful number; the fallback keeps newly forming sections schedulable. `RegistrationFee` already keys on department+level, which the course supplies — no new lookup path.
- **Alternatives considered**: (B) actual-only — zero-student sections would trivially pass and under-count real plans; (C) configured-value-only — legacy `chs` estimate style, ignores roster drift; rejected as untruthful.
- **Design consequence**: The source choice is isolated in `LectureScheduleService::assertVenueCapacity()` so a future policy change is one method (risk R2 mitigation from the How-doc).

## R2. Conflict scoping: academic year + semester

- **Decision**: Venue and section conflict checks run only against sessions in the **same academic year and same semester**. `lecture_schedules.year_id` stores the year (nullable, `nullOnDelete`), stamped at create by the service from `Year::current()` — the established active-year resolver (`app/Models/Year.php`, latest year with a non-DISABLED semester); semester is **derived from the owning course** via `whereHas('course', … semester …)` — never duplicated on the session.
- **Rationale**: Clarification Q2 answer (A). A hall booked last term must not block this term. BR-1 forbids storing course-derived fields; `Course.semester` is the single source (Arabic string mapped through `App\Support\CourseSemesterMapper`).
- **Alternatives considered**: (B) global checks — false rejections across terms, useless history; (C) same semester any year — cross-year false positives; (D) denormalize a `semester` column on sessions — drift risk if a course's semester is edited, violates BR-1.
- **Open edge handled**: `year_id` nullable means legacy/blank-year sessions still scope consistently (null = null in the conflict query).

## R3. Overlap semantics & time handling

- **Decision**: Overlap ⇔ `same day && start < other.end && end > other.start`. Back-to-back (end 10:30 / start 10:30) is **allowed**. Times stored as `TIME` (`HH:MM`), input granularity 15 minutes enforced by validation (`minute % 15 === 0`), end strictly after start. No midnight-spanning sessions.
- **Rationale**: FR-008/FR-010 + edge cases; string `HH:MM` comparison is correct for same-day TIME values in both MySQL and sqlite, so tests mirror production semantics.
- **Alternatives considered**: Storing minutes-since-midnight integers (cleaner arithmetic, but diverges from the `TIME` convention already used by `DailyPaymentDateTime`-style tables and complicates grid rendering); half-open interval `[start, end)` — equivalent to the chosen rule; documented explicitly so boundary tests are unambiguous.

## R4. Concurrency guarantee (FR-020)

- **Decision**: `create()`/`update()` run inside `DB::transaction`, taking `lockForUpdate()` on the venue row and the selected section rows **before** conflict queries, then validating and persisting. The later of two racing submissions fails validation with the normal Arabic conflict message.
- **Rationale**: Check-then-save without locking can interleave two "clean" validations into one conflicting state, breaking SC-001's 100% guarantee. Pessimistic row locks are portable (MySQL row locks; sqlite tests serialize transactions anyway) and trivial at this write volume (a handful of admins).
- **Alternatives considered**: (B) best-effort validation only — explicitly rejected by clarification Q5; (C) DB exclusion constraints — MySQL has no EXCLUDE constraints and overlapping ranges can't be expressed via UNIQUE; (D) advisory locks/queue serialization — extra moving parts unjustified at this scale.

## R5. Section eligibility (BR-2 / FR-005)

- **Decision**: Selectable sections = exactly `$course->sections()` (the `course_section` pivot). Server-side re-validation on every save (`assertSectionsBelongToCourse`) rejects foreign section IDs even if posted directly. Department/level agreement is guaranteed by the existing pivot link; no separate check needed.
- **Rationale**: The pivot is the single source of truth for "this section teaches this course"; re-checking department/level independently would duplicate data the link already implies. Edge case 3 (section later unlinked/reassigned) is handled by re-validation at save + a computed warning on the review screen.
- **Alternatives considered**: Re-checking `section.department_id === course.department_id` as the primary filter — would allow unlinked sections of the same department, contradicting FR-005.

## R6. Range picker ("from X to X")

- **Decision**: `range_from`/`range_to` operate on the course's own section list ordered by section number; a reversed range is **normalized** (min/max) rather than rejected; the resulting IDs are merged with checkbox picks and deduplicated (`array_unique` + pivot `unique(lecture_schedule_id, section_id)` as DB backstop).
- **Rationale**: Edge cases 8 & 11; normalization is friendlier than rejection and matches the shorthand's intent.
- **Alternatives considered**: Reject reversed ranges — more validation noise for a harmless typo.

## R7. UI split: controller CRUD vs Livewire

- **Decision**: Venues = classic `Admin\VenueController` + Blade views cloning the `sections` pages (rarely-changing flat CRUD, exact existing precedent). Sessions = full-page Livewire components (`Index`, `Form`, `WeekGrid`) because capacity totals must update live as sections are ticked (FR-017). Authorization in both: route middleware + `abort_unless` + `@can`.
- **Rationale**: Constitution II (components orchestrate; services decide) and the repo audit — simple lists use controllers, interactive screens use Livewire. No policies exist anywhere in this codebase; none introduced.
- **Alternatives considered**: All-Livewire (venue screen would add reactivity nobody needs); Filament — forbidden by constitution.

## R8. Deletion protection (FR-014)

- **Decision**: Model-level guards via existing `HasDeletionGuards`: `Venue::$blockingRelations = ['lectureSchedules']`; append `lectureSchedules` to `Course` and `Section` blocking lists. DB-level backstop: `venue_id` FK `restrictOnDelete`. `course_id` FK `cascadeOnDelete` is safe because the model guard blocks UI deletion of referenced courses; cascade only fires on intentional hard deletes of the owner.
- **Rationale**: Mirrors the established pattern (How-doc §2); constitution FK policy (cascade = owned rows, restrict = referenced academic data).
- **Alternatives considered**: `restrictOnDelete` on `course_id` too — would break legitimate factory/test cleanup flows that delete a course with its sessions; model guard already covers the user path.

## R9. Enums

- **Decision**: `VenueType`: `Auditorium|Lab|Classroom|Other` (string values `auditorium|lab|classroom|other`) with Arabic `label()` (مدرج/معمل/قاعة دراسية/أخرى). `DayOfWeek`: `Saturday..Thursday` with `label()` (السبت..الخميس) and `order()` for grid sorting; Friday deliberately absent from the enum so it cannot be selected at all (FR-011).
- **Rationale**: Constitution IV (backed enums + `label()`, never raw strings in views); enum-level exclusion of Friday makes invalid days unrepresentable.
- **Alternatives considered**: Including Friday with a validation rule — allows a state the business says is impossible.

## R10. Inactive venues

- **Decision**: Inactive venues are excluded from the new-session venue list; existing sessions in them remain valid and **unflagged** (clarification Q4 answer A). Editing an existing session whose venue is inactive keeps that venue selectable for the save (no forced re-pick), but new sessions cannot choose it.
- **Rationale**: Forward-looking status semantics; avoids silent history rewriting.
- **Alternatives considered**: Flagging existing sessions (rejected in Q4); display-only status (rejected — would let bookings pile into closed rooms).

## R11. Post-hoc over-capacity & orphan flags

- **Decision**: "Over capacity" (venue capacity later lowered) and "orphan section" (section unlinked/reassigned) are **computed at render time** on the review screen — never stored states. No auto-deletion.
- **Rationale**: FR-019 + edge cases 3/10; stored flags would go stale whenever enrollment or capacity changes again.
- **Alternatives considered**: A status column + scheduled recompute — extra machinery for a tiny dataset that can be evaluated in one query per screen.

## R12. Legacy data migration

- **Decision**: **No data migration** from the old `chs` system. Its sections are dynamically computed pseudo-rows (`section_number`) and its `exam_place` CSV import has no conflict/capacity semantics worth preserving. Venues and schedules are entered fresh after go-live.
- **Rationale**: How-doc §13.4 — conversion is not safely possible; the new `Section` entity is first-class.
- **Alternatives considered**: Importing `exam_place` as venues — mixes exam rooms into lecture inventory with wrong shape; deferred to whoever needs it.

## R13. Deployment & rollback

- **Decision**: Purely additive: 3 new tables + `config/permissions.php` entries + idempotent `PermissionsSeeder` run + sidebar links. Deploy = `php artisan migrate --force && php artisan db:seed --class=PermissionsSeeder --force && php artisan optimize:clear`. Rollback = `migrate:rollback` drops only new tables; no existing data touched; new permissions simply go unassigned.
- **Rationale**: Zero downtime, zero risk to money/registration flows (Principle I isolation).
