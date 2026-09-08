# Quickstart: Exam Scheduling, Committees & Seating

**Feature**: `specs/002-exam-scheduling` | Validation guide for proving the feature works end-to-end. Details live in [data-model.md](./data-model.md) and [contracts/](./contracts/); rule sources in [research.md](./research.md) (R1–R13).

## Prerequisites

- PHP 8.4 + Composer, MySQL running locally (Herd), Node for asset build.
- Repo dependencies already installed — **no new packages** (R1: CSV import deferred, so no Excel/CSV libs).
- `venues` table from 001-lecture-scheduling must exist (it does — delivered); at least one active venue seeded.

## Setup

```bash
composer install
npm install && npm run build          # Sneat/Bootstrap assets via Vite
php artisan migrate --force           # 3 additive tables + 6 nullable exam-window columns on years
php artisan db:seed --class=PermissionsSeeder --force   # idempotent: adds exam_schedules.*
php artisan optimize:clear            # refresh permission/sidebar caches
```

Grant `exam_schedules.*` permissions to a staff user from the existing users screen; log in to the admin portal. Optionally extend `DemoDataSeeder` with a full-term scenario (courses, approved registrations across two overlapping courses, venues, committees).

## Automated validation (definition of done)

```bash
php artisan test --compact --filter=ExamSchedule    # service + seating + Livewire + student + print suites
php artisan test --compact                          # full suite — no regressions
vendor/bin/pint --dirty --format agent              # style clean
```

Expected: all `tests/Feature/ExamSchedule/` specs green. Coverage map:

| Area | Must prove |
|------|-----------|
| Audience (R3) | examinees = approved only; pending/rejected/cancelled and soft-deleted students excluded |
| Student conflict (FR-005) | overlap rejected with student names + both courses; **11:00→11:00 adjacency accepted**; different dates accepted; different year/semester independent (R2) |
| Venue/committee conflict (FR-006) | same venue overlapping same date rejected; edited session excluded from its own check (FR-009) |
| Uniqueness (FR-002) | second Regular session for same course+year+semester+type rejected; Resit for the same course+term accepted (FR-007) |
| Time/window rules (FR-003) | end ≤ start rejected; off-15-minute times rejected; date outside configured window rejected with Arabic range message; **unset window accepts any date** (R6) |
| Concurrency (R5) | sequential double-submit: second overlapping save rejected, one row persists; lock-before-check ordering asserted (barrier or `DB::listen`) — sqlite cannot reproduce a real OS-level race |
| Distribution (R7) | 120 examinees → two committees of 60 split alphabetically; seat numbers unique per committee; regeneration keeps manual moves, absorbs newcomers, drops ineligible (SC-006) |
| Capacity (FR-013) | 120 examinees vs 100 seats → exact message "عدد الممتحنين (120) يتجاوز إجمالي سعة اللجان (100)" and zero DB change (transaction) |
| Stale flag (R9) | new approved registration after generation ⇒ stale ⇒ publish blocked until regeneration |
| Over-capacity committee (FR-018) | committee capacity reduced below its already-placed students ⇒ flagged over capacity (stale), publish blocked, no student silently removed |
| Publish gate (R10) | blocked on: 0 examinees, stale seating, any conflict; per-session — publishing session A leaves session B draft and invisible |
| Edit revert (FR-020) | editing a published session returns it to draft; student view loses it immediately |
| Permissions (FR-024) | guest / permission-less user → 403 on every route and every Livewire action incl. direct `publish()` calls |
| Student view (FR-021) | zero rows before first publish (SC-005); after publish, full chronological table with venue/committee/seat; unscheduled courses show "لم يُحدد بعد" |
| Print (FR-022) | committee sheet rows == students' personal-table rows for that committee (SC-004); student schedule print matches dashboard |

## Manual end-to-end walkthrough

1. **Board**: open جدول الامتحانات → defaults to current year+semester → confirm only courses with approved registrations appear, with examinee counts and "لم تُحدد" state.
2. **Session**: create Statistics — 2026-01-15, 9:00–11:00, Regular, venue "مدرج أ" with committees 60+60 → save → toast, state مسودة, live capacity counter showed 120/120.
3. **Conflict**: create a second course's session overlapping 10:30–12:00 in the same hall → rejected, message names the conflicting session; schedule a shared student's second exam at the same time → rejected with the student's name.
4. **Distribution**: press "توليد التوزيع" → tabs per committee, 60+60 alphabetical, seat numbers 1–60 each; manually move one student → regenerate → the moved student stays put, nothing else changes.
5. **Late registration**: approve a new registration for the course → badge "توزيع غير محدّث" appears → publish refused → regenerate → badge clears.
6. **Publish**: publish Statistics → student logs in → sees exactly that exam (day/date/time/venue/committee/seat) + print works; other draft sessions invisible.
7. **Revert**: edit the published session's time → back to مسودة → student view loses it instantly.
8. **Attendance**: print committee sheet from admin → names/seat numbers identical to students' tables.
9. **Resit**: create a Resit session for the same course+term → accepted (type distinguishes it).

## Rollback safety

`php artisan migrate:rollback` drops only exam tables/columns; registrations, grades, venues, and lecture schedules are untouched (all changes additive). New permissions are invisible in the UI until granted — safe dark launch.
