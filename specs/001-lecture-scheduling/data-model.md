# Phase 1 Data Model: Lecture & Venue Scheduling

**Feature**: `specs/001-lecture-scheduling` | **Date**: 2026-09-08
Research references: [research.md](./research.md) (R1–R13). All tables are **new and additive** — no existing table is altered.

## Entity Relationship Overview

```text
Department ─┐                       ┌── Year (existing, referenced)
Level ──────┼─► Course (existing) ──┤
            │        │ 1            │
            │        ▼ N            │
            │   LectureSchedule ◄───┘ (year_id, nullable)
            │        │ N        ▲ 1
            │        ▼ pivot    │
            │  lecture_schedule_section
            │        │ N        │
Section (existing) ──┘          │
   │ 1                          │
   ▼ N                          │
Student (existing)     Venue (NEW) ── 1:N ── LectureSchedule
Course ◄──N:M──► Section  (via existing course_section pivot — eligibility source, R5)
```

## New Tables

### 1. `venues` — migration `create_venues_table`

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | bigIncrements | PK | |
| name | string(255) | **unique** | FR-001; Arabic UI copy, e.g. "مدرج أ" |
| type | string | cast `VenueType` enum | auditorium / lab / classroom / other |
| capacity | unsignedInteger | **nullable** | null ⇒ capacity check skipped + notice (R1) |
| is_active | boolean | default true | inactive ⇒ not selectable for NEW sessions (R10) |
| notes | text | nullable | free-form |
| timestamps | — | `timestamps()` | constitution requirement |

**Model**: `App\Models\Venue` — `use HasDeletionGuards`, `$blockingRelations = ['lectureSchedules']`; `casts()` method style; `lectureSchedules(): HasMany`.

**States**: Active ⇄ Inactive (toggle; no other lifecycle — deletion is guarded, not soft-delete).

### 2. `lecture_schedules` — migration `create_lecture_schedules_table`

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | bigIncrements | PK | |
| course_id | foreignId | FK → courses, **cascadeOnDelete** | session owned by exactly one course (FR-002/004) |
| venue_id | foreignId | FK → venues, **restrictOnDelete** | DB backstop for FR-014 (R8) |
| year_id | foreignId | FK → years, **nullable, nullOnDelete** | stamped from `Year::current()` inside the service at create (null when no active year); conflict scoping context (R2) |
| day | string | cast `DayOfWeek` enum | Saturday..Thursday only — Friday unrepresentable (R9) |
| start_time | time | required | `HH:MM`, 15-min granularity (FR-010) |
| end_time | time | required, > start_time | same day, no midnight span |
| timestamps | — | `timestamps()` | |

**Indexes**: `['venue_id','day','start_time','end_time']` (venue conflict scan), `['course_id','day']` (per-course grid).

**Derived, never stored** (BR-1 / FR-004): department, level, semester — always read through `course`.

**Model**: `App\Models\LectureSchedule` — relations `course()`, `venue()`, `year()`, `sections(): BelongsToMany (lecture_schedule_section)`; helpers `durationInMinutes(): int`, `overlaps(LectureSchedule $other): bool` (same day && `start < other.end && end > other.start`, R3). Student counting is NOT on the model — the single authoritative implementation is `LectureScheduleService::selectedStudentsCount()` (R1), shared by the Form preview and the server check.

### 3. `lecture_schedule_section` (pivot) — migration `create_lecture_schedule_section_table`

| Column | Type | Constraints |
|--------|------|-------------|
| lecture_schedule_id | foreignId | FK → lecture_schedules, cascadeOnDelete |
| section_id | foreignId | FK → sections, cascadeOnDelete |
| — | composite | **unique(lecture_schedule_id, section_id)** — DB dedupe backstop (R6) |

No model class; accessed via `belongsToMany`.

## Modified Existing Models (no schema change)

| Model | Change |
|-------|--------|
| `Course` | + `lectureSchedules(): HasMany`; append `'lectureSchedules'` to `$blockingRelations` (FR-014) |
| `Section` | + `lectureSchedules(): BelongsToMany`; append `'lectureSchedules'` to `$blockingRelations` (FR-014) |

## New Enums (`app/Enums/`)

| Enum | Cases (key = value) | Arabic `label()` | Extra |
|------|---------------------|-------------------|-------|
| `VenueType` | Auditorium=`auditorium`, Lab=`lab`, Classroom=`classroom`, Other=`other` | مدرج، معمل، قاعة دراسية، أخرى | — |
| `DayOfWeek` | Saturday=`saturday` … Thursday=`thursday` | السبت … الخميس | `order(): int` for grid sort; **no Friday case** |

## Validation Rules (enforced in `LectureScheduleService` + request/component layer)

### Venue (Form Requests `Store/UpdateVenueRequest`)

| Field | Rules | Arabic message theme |
|-------|-------|----------------------|
| name | required, string, max 255, unique on create / unique-except-self on update | "اسم المكان مستخدم بالفعل" |
| type | required, in enum values | — |
| capacity | nullable, integer, min 1 | — |
| is_active | boolean | — |

### Lecture Session (Livewire `Form::rules()` + service re-check, R5)

| Field | Rules |
|-------|-------|
| venue_id | required; must reference an **active** venue (new sessions; existing venue kept editable when inactive, R10) |
| day | required; must be a `DayOfWeek` case |
| start_time, end_time | required `HH:MM`; end > start; both on 15-minute boundary |
| section_ids | required, non-empty; every ID must belong to `$course->sections()` (FR-005); deduplicated; range input normalized min/max (R6) |

### Service Invariants (single source of truth; all scoped to same `year_id` — stamped at create via `Year::current()` — + course semester, R2)

| # | Invariant | Failure |
|---|-----------|---------|
| V1 | Σ per-section students (actual enrollment; configured fallback when 0, R1) ≤ venue.capacity — skipped when capacity null | `LectureScheduleConflictException` — "الإجمالي المختار N طالب يتجاوز سعة {venue} (M)" |
| V2 | No other session in same venue + day with overlapping time (adjacency OK) — excluding `$ignoreScheduleId` (FR-013) | Exception naming conflicting course/venue/day/time (FR-016) |
| V3 | No selected section in another session same day overlapping — across ANY course/venue, excluding self | Exception naming the clashing session |
| V4 | All section IDs currently linked to the course | Exception; stale links surface as review-screen warning, not deletion (R11) |
| V5 | V1–V4 evaluated inside `DB::transaction` with `lockForUpdate` on venue + section rows (R4) | Second racing save rejected with normal message |

### Computed Review States (render-time only, R11)

- **Over capacity**: venue.capacity < session's current Σ students → badge "تجاوز السعة".
- **Orphan section**: a session's section no longer linked to the course → warning; section blocked from new sessions.

## Factories & Seeders

- `VenueFactory` with states `auditorium()`, `lab()`; `LectureScheduleFactory` (sequence-safe times on 15-min boundaries).
- `DemoDataSeeder`: append venues + a small conflict-free schedule block for the demo environment.
