# Phase 1 Data Model: Exam Scheduling, Committees & Seating

**Feature**: `specs/002-exam-scheduling` | **Date**: 2026-09-08
Research references: [research.md](./research.md) (R1–R13). All changes are **additive** — three new tables plus six nullable columns on `years`; no existing column is altered.

## Entity Relationship Overview

```text
Course (existing) ──1:N──► ExamSession ◄──N:1── Year (existing, + exam-window columns)
     ▲                        │ 1                    ▲
     │ approved audience      │                      │
Registration ──┐               ▼ N                Registration (existing) ─┐
(existing)     ├──────────► ExamCommittee ◄──venue── Venue (existing, 001) │
RegistrationCourse ─────────┐   │ 1                                       │
(existing, audience join)   │   ▼ N                                       │
Student (existing) ─────────┴── ExamSeatAssignment ──► Student (existing) │
                               (one per session+student)                  │
Examinee audience = derived query over Registration ⋈ RegistrationCourse ─┘ (R3)
```

## New Tables

### 1. `exam_sessions` — migration `create_exam_sessions_table`

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | bigIncrements | PK | |
| course_id | foreignId | FK → courses, **cascadeOnDelete** | session owned by exactly one course; model guard blocks UI deletion (R11) |
| year_id | foreignId | FK → years, **NOT NULL, restrictOnDelete** | chosen on the board (default `Year::current()`); conflict-scoping context (R2, R11) |
| semester | string | cast `Semester` enum (`first/second/summer`) | same convention as `registrations.semester` |
| type | string | cast `ExamType` enum, default `regular` | regular / resit / improvement (FR-007) |
| exam_date | date | required | inside the semester's exam window when configured (R6) |
| start_time | time | required | `HH:MM`, 15-min granularity (R2) |
| end_time | time | required, > start_time | same day, no midnight span |
| status | string | cast `ExamSessionStatus`, default `draft` | draft / published (R10) |
| notes | text | nullable | free-form |
| timestamps | — | `timestamps()` | constitution requirement |

**Indexes / constraints**:
- `unique(course_id, year_id, semester, type)` — BR-1 / FR-002, DB-level backstop for the atomic-save guarantee (R5).
- `index(year_id, semester, exam_date)` — conflict scans (R2, R4).

**Derived, never stored**: department, level, section lists, examinee audience, staleness — all computed (R3, R9).

**Model**: `App\Models\ExamSession` — `use HasDeletionGuards`, `$blockingRelations = ['committees', 'seatAssignments']`; relations `course()`, `year()`, `committees(): HasMany`, `seatAssignments(): HasMany`; helper `overlaps(ExamSession $other): bool` (same date && `start < other.end && end > other.start`, R2).

**State machine**:

```text
draft ──publish(): assertPublishable() under per-year lock──► published
  ▲                                                              │
  └────── any edit (session fields / committees / distribution) ◄─┘
                     (automatic revert, FR-020)
Orthogonal derived flag: "distribution out of date" = audience ≠ assignments (R9)
⇒ blocks publish until generateDistribution() runs (FR-017)
```

### 2. `exam_committees` — migration `create_exam_committees_table`

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | bigIncrements | PK | |
| exam_session_id | foreignId | FK → exam_sessions, **cascadeOnDelete** | owned rows |
| venue_id | foreignId | FK → venues, **restrictOnDelete** | referenced academic data (R11); active venues only in pickers (R12) |
| name | string(255) | required | "لجنة 1" / "أ" — Arabic UI copy |
| capacity | unsignedInteger | required, ≥ 1 | seats in this committee (FR-011) |
| timestamps | — | `timestamps()` | |

**Indexes / constraints**: `unique(exam_session_id, name)`; `index(venue_id, ...)` served by the session's date/time for venue-conflict scans (FR-006: two committees of one session share a venue — venue conflict is checked at **session** level, committee-level conflict at committee level).

**Model**: `App\Models\ExamCommittee` — relations `examSession()`, `venue()`, `assignments(): HasMany`; helpers `assignedCount(): int`, `isFull(): bool`.

**Validation**: capacity reduction below `assignedCount()` is allowed at save but forces regeneration and blocks publish — no silent student removal (FR-018).

### 3. `exam_seat_assignments` — migration `create_exam_seat_assignments_table`

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | bigIncrements | PK | |
| exam_session_id | foreignId | FK → exam_sessions, **cascadeOnDelete** | denormalized for unique constraints + fast per-session reads |
| exam_committee_id | foreignId | FK → exam_committees, **cascadeOnDelete** | placement |
| student_id | foreignId | FK → students, **cascadeOnDelete** | student |
| seat_number | string | required | unique per committee; stable per student across regenerations (gaps allowed, never renumbered — R7); string keeps legacy 5-digit format expressible (R8) |
| timestamps | — | `timestamps()` | |

**Indexes / constraints**:
- `unique(exam_session_id, student_id)` — one student = exactly one committee per session (FR-012).
- `unique(exam_committee_id, seat_number)` — BR-6 / FR-014 at DB level.

**Model**: `App\Models\ExamSeatAssignment` — relations `examSession()`, `committee()`, `student()`. Thin data holder; all generation logic lives in `ExamSeatingService` (constitution II).

## Altered Table

### 4. `years` — migration `add_exam_periods_to_years_table` (6 nullable columns)

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| first_semester_exam_from | date | nullable | exam window for `Semester::FIRST` (R6) |
| first_semester_exam_to | date | nullable | |
| second_semester_exam_from | date | nullable | exam window for `Semester::SECOND` |
| second_semester_exam_to | date | nullable | |
| summer_exam_from | date | nullable | exam window for `Semester::SUMMER` |
| summer_exam_to | date | nullable | |

**Rule**: window enforced at save time for new/edited sessions **only when both dates are set**; unset ⇒ any date accepted; later configuration never retroactively invalidates saved sessions (clarification 2026-09-08, R6). `complete down()` drops all six columns explicitly.

## Existing Entities (read-only reliance)

- **Course** (existing): EDIT `examSessions(): HasMany` + append to `$blockingRelations`.
- **Year** (existing): EDIT `examSessions(): HasMany` + append to `$blockingRelations` + exam-window columns above.
- **Registration / RegistrationCourse** (existing): audience source — approved, same year+semester, course linked (R3). Never written by this feature.
- **Student** (existing): name drives distribution order; `section_id`/`level_id` feed board filters and print sheets; soft-deleted students excluded via active-registration query.
- **Venue** (existing, from 001): committee rooms; EDIT `$blockingRelations += ['examCommittees']` + `examCommittees(): HasMany`.

## Enums (new, `app/Enums/`)

| Enum | Cases (values) | Arabic `label()` |
|------|----------------|------------------|
| `ExamType` | `Regular='regular'`, `Resit='resit'`, `Improvement='improvement'` | عادي / فصل ثانٍ / تحسين |
| `ExamSessionStatus` | `Draft='draft'`, `Published='published'` | مسودة / منشور (+ `badgeClass()`, pattern: `MilitaryEducationCourseStatus`) |

## Service Contracts (where the rules live)

### `ExamScheduleService`

| Method | Contract |
|--------|----------|
| `examinees(ExamSession $session): Collection<int, Student>` | R3 single-query audience, name-ordered |
| `validateSession(ExamSession $session): void` | time order + granularity + exam window (R6) + uniqueness + venue/committee overlap (FR-006) + student overlap (FR-005); throws `ExamScheduleException` with Arabic report naming affected students/courses or conflicting session |
| `findStudentConflicts(ExamSession $session): Collection<int, StudentConflict>` | R4 single query |
| `assertPublishable(ExamSession $session): void` | examinees > 0 ∧ ¬stale ∧ zero conflicts ∧ capacity (R10) |
| `publish(ExamSession $session): void` / `unpublish(...)` | transaction + per-year `lockForUpdate` + re-check (R5) |
| `create(array $attributes, array $committees): ExamSession` / `update(...)` | atomic save under per-year lock (R5); editing a published session reverts to draft (FR-020) |

### `ExamSeatingService`

| Method | Contract |
|--------|----------|
| `generateDistribution(ExamSession $session): void` | R7 incremental algorithm; capacity overflow ⇒ typed exception + rollback |
| `moveStudent(ExamSeatAssignment $assignment, ExamCommittee $target): void` | manual placement at next free seat in target committee; capacity-checked |
| `isSeatingStale(ExamSession $session): bool` | R9 derived comparison — audience ≠ assignments OR any committee over capacity (FR-018) |

**Out of phase 1** (R1): `importFromCsv()`, `exams:import-legacy` command — deferred by clarification; the service above is the single write seam any future importer must reuse.
