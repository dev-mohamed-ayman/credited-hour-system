# Contract: Venue CRUD (Controller + Form Requests)

**Feature**: Lecture & Venue Scheduling | Follows the existing `SectionController` + `StoreSectionRequest` pattern verbatim (constitution II: classic CRUD → Form Requests).

## `Admin\VenueController`

| Action | Behavior | Authorization |
|--------|----------|---------------|
| index | Paginated list of venues: name, type label (`VenueType::label()`), capacity (or "غير محددة"), active badge; edit/delete buttons | route `permission:venues.view`; delete button `@can('venues.delete')` |
| create / edit | Blade form (name, type select, capacity, is_active, notes) | `permission:venues.create` / `.edit` |
| store | Validate → `Venue::create` → redirect to index + success toast | `permission:venues.create` |
| update | Validate (unique-except-self) → save → redirect + toast | `permission:venues.edit` |
| destroy | `HasDeletionGuards`: if `hasBlockingRelations()` → block with `getBlockingRelationsMessage()` (Arabic), no delete; else delete + toast | `permission:venues.delete` |

Views: `resources/views/admin/pages/venue/{index,create,edit}.blade.php` (clone of `admin/pages/section/*`).

## `StoreVenueRequest` / `UpdateVenueRequest`

| Field | `Store` rules | `Update` rules | Arabic message theme |
|-------|---------------|----------------|----------------------|
| name | `required|string|max:255|unique:venues,name` | `required|string|max:255|unique:venues,name,{id}` | "اسم المكان مطلوب" / "هذا الاسم مستخدم بالفعل" |
| type | `required|in:auditorium,lab,classroom,other` (from `VenueType`) | same | "نوع المكان مطلوب" |
| capacity | `nullable|integer|min:1` | same | "السعة يجب أن تكون رقمًا موجبًا" |
| is_active | `nullable|boolean` | same | — |
| notes | `nullable|string|max:1000` | same | — |

- `authorize()` returns true (route middleware already gates); `type` values sourced from `VenueType::cases()` rather than a hardcoded string list.
- On `store`/`update` success the controller dispatches the toast event bridge (constitution IV); destructive `destroy` uses `window.confirmAction()` before the request.

## Deletion guard contract (FR-014)

- `Venue::$blockingRelations = ['lectureSchedules']`.
- Attempting to delete a venue with ≥1 session MUST return the Arabic guard message (e.g. "لا يمكن حذف 'مدرج أ' لارتباطه بـ 3 جدول محاضرات") and MUST NOT remove the row.
- DB-level `venue_id` FK is `restrictOnDelete` as a second line of defense (research R8).
