# Contract: Student View & Print Pages

**Feature**: Exam Scheduling, Committees & Seating | Arabic-first, RTL, Sneat/Bootstrap only; print pages clone the structure of `admin/pages/student/print_seat_number.blade.php` (browser print, no PDF packages).

## `App\Livewire\Student\ExamSchedule` — personal exam table (FR-021)

**Guard**: `student` only; queries strictly `auth()->user()`-scoped — no parameters that could leak another student's data.

**Data**: published sessions (R10) of the student's **approved** registrations for the current year + current semester, ordered by `exam_date, start_time`. Per row:

| Column | Source |
|--------|--------|
| المادة | course name |
| اليوم | Arabic weekday derived from `exam_date` (Carbon) |
| التاريخ | `exam_date` (`Y-m-d` display) |
| الميعاد | `start_time–end_time` |
| المكان | committee → venue name |
| اللجنة | committee name |
| رقم الجلوس | seat assignment `seat_number` |

**Rules**:
- Draft/unpublished sessions are invisible — zero rows before the first publish (SC-005).
- Approved courses with no scheduled session render "لم يُحدد بعد" below the table (useful legacy behavior, R13).
- Seating must exist for a published session (publish gate guarantees it, R10) — if data is ever missing, the row shows the exam without committee/seat and the quickstart flags it as a bug.
- Print button → `window.print()` on the student's own table (browser print of the block/page — students cannot reach staff-guard routes).

## `ExamPrintController@studentSchedule(Student $student)` (staff print, FR-021)

Same table rendered standalone for counter staff; permission `students.view` (staff guard).

## `ExamPrintController@committeeSheet(ExamCommittee $committee)` — attendance sheet (FR-022)

**Header**: faculty/system title, course name, exam type label, date, time window, venue, committee name, total students.
**Table**: seat number / student code / full name / section — ordered by `seat_number` — plus an empty signature column per row.
**Footer**: "آخر تحديث" timestamp of the committee's latest assignment change (mitigates stale printed copies, How-doc R5).
**Contract**: the sheet's rows MUST equal the students those students see on their personal tables for that session (SC-004 — same source, no separate storage).

## Print behavior

Both pages: `@media print`-friendly standalone Blade (no admin layout chrome), `window.print()` trigger button hidden in print output. No new npm/composer dependencies.
