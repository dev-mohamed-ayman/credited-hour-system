# Quickstart: Lecture & Venue Scheduling

**Feature**: `specs/001-lecture-scheduling` | Validation guide for proving the feature works end-to-end. Details live in [data-model.md](./data-model.md) and [contracts/](./contracts/).

## Prerequisites

- PHP 8.4 + Composer, MySQL running locally (Herd), Node for asset build.
- Repo dependencies already installed — **no new packages** (research R13).

## Setup

```bash
composer install
npm install && npm run build          # Sneat/Bootstrap assets via Vite
php artisan migrate --force           # 3 additive tables: venues, lecture_schedules, lecture_schedule_section
php artisan db:seed --class=PermissionsSeeder --force   # idempotent: adds venues.* + lecture_schedules.*
php artisan optimize:clear            # refresh permission/sidebar caches
```

Grant the new permissions to a staff user from the existing `users` screen, then log in to the admin portal.

## Automated validation (definition of done)

```bash
php artisan test --compact --filter=LectureSchedule   # service + Livewire + venue CRUD suites
php artisan test --compact                            # full suite — no regressions
vendor/bin/pint --dirty --format agent                # style clean
```

Expected: all `tests/Feature/LectureSchedule/` specs green. Coverage map (see contracts for rule sources):

| Area | Must prove |
|------|-----------|
| Service — capacity | overrun rejected; `capacity=null` skipped; zero-enrollment section uses configured fallback (R1) |
| Service — venue conflict | overlap rejected; **10:30→10:30 adjacency accepted**; different days accepted |
| Service — section conflict | clash across two different courses rejected |
| Service — membership | section from another course/department rejected server-side (FR-005) |
| Service — edit | `ignoreScheduleId` lets a session change time without clashing with itself (FR-013) |
| Service — scoping | same hall reused in a different year/semester is NOT a conflict (R2); `year_id` stamped from `Year::current()` at create; null-year sessions scope only against null-year sessions |
| Service — time rules | end ≤ start rejected; off-15-minute-boundary times rejected with Arabic message (FR-010) |
| Service — concurrency | sequential double-submit: second overlapping save rejected, one row persists; lock-before-check ordering asserted (barrier or `DB::listen`) — sqlite cannot reproduce a real OS-level race (FR-020, R4) |
| Permissions | guest / permission-less user → 403 on every route and every Livewire action |
| Livewire create | valid session → `assertDatabaseHas` schedule + pivot rows + success toast |
| Livewire conflict | rejected → Arabic message, **no DB row created** |
| Range picker | "1→10" yields correct IDs; "10→1" normalized; checkbox+range overlap deduped (R6) |
| Venue CRUD | unique-name enforced; delete of a referenced venue blocked with guard message (FR-014) |
| Inactive venue | not offered for a new session; existing session in it stays valid & unflagged (R10) |

## Manual validation scenarios

1. **US1 — schedule a course (P1)**
   - Create venue "مدرج أ" (مدرج, 300). Open a course linked to sections 1–12.
   - Add session: مدرج أ · الأحد · 9:00–10:30 · range 1→10. Live total shows ~280/300 (green). Save → appears in list + grid.
   - Try 10:00–11:30 same hall → rejected with Arabic message naming the clashing course/day/time.
   - Try a lab (capacity 40) with sections totaling 60 → rejected: "الإجمالي المختار 60 طالب يتجاوز سعة المعمل (40)".
2. **US2 — venues (P2)**
   - Duplicate name "مدرج أ" → rejected. Delete a venue that has sessions → blocked with guard message.
3. **US3 — weekly review (P3)**
   - Open the course WeekGrid → both weekly sessions sit in correct day/time cells; lower the venue capacity below attendance → row flags "تجاوز السعة" (no auto-delete).
4. **Security**
   - As a user without `lecture_schedules.create`, hit `/lecture-schedules/{course}/create` → 403; sidebar links hidden.

## Success-criteria spot checks

- **SC-001**: no combination of the above can leave a conflicting/over-capacity row in the DB.
- **SC-003**: a typical session takes < 1 min via range picker + live capacity.
- **SC-004**: every rejection names course + venue/section + day + time.

## Rollback

```bash
php artisan migrate:rollback --step=3   # drops only the 3 new tables; existing data untouched
```

New permissions simply go unassigned; no legacy data migration exists (research R12).
