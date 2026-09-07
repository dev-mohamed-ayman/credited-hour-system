# Contract: Routes × Permissions Matrix

**Feature**: Lecture & Venue Scheduling | All routes live in the existing admin group of `routes/web.php` (single-file routing convention). Every route is permission-gated at the middleware layer; components/controllers re-check server-side (constitution III).

## Venues (classic controller CRUD — mirrors `sections`)

| Method | URI | Name | Handler | Middleware |
|--------|-----|------|---------|------------|
| GET | `/venues` | `venues.index` | `Admin\VenueController@index` | `permission:venues.view` |
| GET | `/venues/create` | `venues.create` | `@create` | `permission:venues.create` |
| POST | `/venues` | `venues.store` | `@store` | `permission:venues.create` |
| GET | `/venues/{venue}/edit` | `venues.edit` | `@edit` | `permission:venues.edit` |
| PUT/PATCH | `/venues/{venue}` | `venues.update` | `@update` | `permission:venues.edit` |
| DELETE | `/venues/{venue}` | `venues.destroy` | `@destroy` | `permission:venues.delete` |

`Route::resource('venues', VenueController::class)->except(['show'])` — no show page (flat list, like sections).

## Lecture Schedules (full-page Livewire)

| Method | URI | Name | Component | Middleware |
|--------|-----|------|-----------|------------|
| GET | `/lecture-schedules` | `lecture-schedules.index` | `Livewire\Admin\LectureSchedule\Index` | `permission:lecture_schedules.view` |
| GET | `/lecture-schedules/{course}/create` | `lecture-schedules.create` | `...\Form` (create mode) | `permission:lecture_schedules.create` |
| GET | `/lecture-schedules/{schedule}/edit` | `lecture-schedules.edit` | `...\Form` (edit mode) | `permission:lecture_schedules.edit` |

Destructive actions (session delete) are Livewire methods on `Index`, not routes; guarded by `abort_unless(auth()->user()->can('lecture_schedules.delete'), 403)` inside the action.

## Permissions (added to `config/permissions.php`, seeded by `PermissionsSeeder`)

| Module | Label (ar) | Actions |
|--------|------------|---------|
| `venues` | الأماكن والمدرجات | view=عرض, create=إنشاء, edit=تعديل, delete=حذف |
| `lecture_schedules` | جدول المحاضرات | view=عرض, create=إنشاء, edit=تعديل, delete=حذف |

## Sidebar (`resources/views/admin/layouts/sidebar.blade.php`)

Two links inside the existing "المواد الدراسية" group next to `courses.index`, each wrapped in `@can`:
- جدول المحاضرات → `lecture-schedules.index` (`@can('lecture_schedules.view')`)
- الأماكن والمدرجات → `venues.index` (`@can('venues.view')`)

## Model binding & scoping

- `{course}` binds `Course`; `{schedule}` binds `LectureSchedule` (route-model binding, default).
- `{venue}` deletion relies on `HasDeletionGuards` → blocked response when `lectureSchedules` exist (FR-014).
