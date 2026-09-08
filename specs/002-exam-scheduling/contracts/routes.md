# Contract: Routes × Permissions Matrix

**Feature**: Exam Scheduling, Committees & Seating | All admin routes live in the existing admin group of `routes/web.php` (single-file routing convention); student route lives in the existing `student` prefix group. Every route is permission/guard-gated at the middleware layer; components/controllers re-check server-side (constitution III).

## Exam Schedules — Admin (full-page Livewire)

| Method | URI | Name | Component | Middleware |
|--------|-----|------|-----------|------------|
| GET | `/exam-schedules` | `exam-schedules.index` | `Livewire\Admin\ExamSchedule\Index` | `permission:exam_schedules.view` |
| GET | `/exam-schedules/{course}/create` | `exam-schedules.create` | `...\Form` (create mode) | `permission:exam_schedules.create` |
| GET | `/exam-schedules/{session}/edit` | `exam-schedules.edit` | `...\Form` (edit mode) | `permission:exam_schedules.edit` |
| GET | `/exam-schedules/{session}/seating` | `exam-schedules.seating` | `...\Seating` | `permission:exam_schedules.edit` |

Destructive and state actions (session delete, publish, unpublish, generate distribution, move student) are Livewire methods, not routes; each re-checks its own permission with `abort_unless(auth()->user()->can('exam_schedules.<action>'), 403)` inside the action (publish/unpublish require `exam_schedules.publish`).

## Print (classic controller — browser print, no interactivity)

| Method | URI | Name | Handler | Middleware |
|--------|-----|------|---------|------------|
| GET | `/exam-schedules/print/committee/{committee}` | `exam-schedules.print.committee` | `Admin\ExamPrintController@committeeSheet` | `permission:exam_schedules.view` |
| GET | `/exam-schedules/print/student/{student}` | `exam-schedules.print.student` | `Admin\ExamPrintController@studentSchedule` | `permission:students.view` |

## Student portal (full-page Livewire block on dashboard)

| Method | URI | Name | Component | Middleware |
|--------|-----|------|-----------|------------|
| GET | `/student/exam-schedule` | `student.exam-schedule` | `Livewire\Student\ExamSchedule` | `student` guard (auth); rendered as a dashboard block and as a standalone page |

Students see only `Published` sessions of their own approved registrations — no permission checks apply on the student guard; ownership is enforced by querying only `auth()->user()`'s own data.

## Exam window editing

No new route: the six exam-window date fields (R6) are added to the existing Year Settings screen (`YearSettings` component) and validated under the existing year-settings permission.

## Permissions (added to `config/permissions.php`, seeded idempotently by `PermissionsSeeder`)

| Module | Label (ar) | Actions |
|--------|------------|---------|
| `exam_schedules` | جدول الامتحانات | view=عرض, create=إنشاء, edit=تعديل, delete=حذف, publish=نشر |

No `import` action in phase 1 (R1 — CSV import deferred).

## Sidebar (`resources/views/admin/layouts/sidebar.blade.php`)

One link inside the existing "المواد الدراسية" group, next to the 001 lecture-scheduling links, wrapped in `@can('exam_schedules.view')`:
- جدول الامتحانات → `exam-schedules.index`
