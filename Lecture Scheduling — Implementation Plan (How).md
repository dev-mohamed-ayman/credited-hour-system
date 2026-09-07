# تحديد المحاضرات والمدرجات — خطة التنفيذ (How)

> **ملف مكمل لـ:** `Lecture Scheduling — Feature Spec (What & Why).md`
> **المشروع:** `/Users/mohamedayman/Herd/D-Informatics/credited-hour-system`

---

## 1. فحص الـRepository — الـArchitecture الموجودة

| العنصر | الوضع الحالي |
|---|---|
| Stack | Laravel 12 · PHP 8.4 · Livewire 3 · Tailwind 4 (قالب Tabler) · Pest 4 · Spatie Permission · Pint |
| UI Pattern | صفحات Admin بسيطة = Controller + Blade (`sections`, `levels`, `courses` form) · الشاشات التفاعلية = Livewire components في `app/Livewire/Admin/<Domain>/` مع `->extends('admin.layouts.app')->section('content')` |
| Authorization | **لا يوجد `app/Policies`** — الاعتماد على Spatie permissions: `permission:x.y` middleware في `routes/web.php` + `abort_unless(auth()->user()->can(...), 403)` داخل الـLivewire actions. التعريفات في `config/permissions.php` و`PermissionsSeeder` |
| Validation | Form Requests في `app/Http/Requests/Admin/` للـCRUD التقليدي، و`protected function rules()` داخل مكوّنات Livewire (مثل `CourseForm`) |
| Domain Logic | `app/Services/` (مثال: `CoursePrerequisiteValidator`, `MilitaryEducationService`) |
| Deletion Safety | Trait `HasDeletionGuards` مع `$blockingRelations` على كل موديل مرتبط |
| Enums | `app/Enums/` بحالة TitleCase keys و`label()` عربية (مثال: `Semester`) |
| Localization | كل نصوص الـUI والـmessages بالعربية |

### السيستم القديم (`chs`) — الدروس المستفادة
- الشعب **كيان وهمي محسوب ديناميكيًا**: `updateSectionNumber` في `AdminController.php:833` يقسّم الطلاب على شعب حسب `study_group_1..4` (عدد طلاب لكل فرقة) ويكتب في جدول `section_number` — مع حالة خاصة "سكشن الباقون" للراسبين. **لا نكرر هذا**: النظام الحالي شعبه real entities.
- الأماكن كانت `exam_place` فقط (CSV import في `StudentAffairsController.php:2485`) بلا أي فحص تعارض/سعة. **نستفيد منه كـ anti-pattern** ونضع كل الـvalidation في Service مُختبَر.

---

## 2. الموديلات الموجودة وإيه اللي هنستخدمه

| موديل موجود | الاستخدام في الخاصية الجديدة |
|---|---|
| `Course` | الأب المنطقي للجلسات (`hasMany`)؛ مصدر department/level/semester؛ إضافة `lectureSchedules` لـ`$blockingRelations` |
| `Section` | الشعب الحاضرة (pivot)؛ عدد الطلاب الفعلي عبر `students()`؛ إضافة `lectureSchedules` لـ`$blockingRelations` |
| `Level` / `Department` | فلترة الشعب المتاحة (BR-2) |
| `Student` | عدّاد السعة الفعلية (`section_id`, softDeletes) |
| `Year` | scoping للجلسات (قرار Q3) |
| `RegistrationFee` | `number_of_students_per_section` كمصدر احتياطي للسعة (Q1) |
| `MilitaryEducationCourse` | **نموذج مرجعي** لنمط capacity + status |

**موديلات جديدة:** `Venue`, `LectureSchedule` (+ pivot `lecture_schedule_section` بدون موديل مستقل — `belongsToMany`).

---

## 3. Database Changes (3 migrations — additive بالكامل)

### 3.1 `create_venues_table`
```php
$table->id();
$table->string('name')->unique();
$table->string('type');                    // App\Enums\VenueType
$table->unsignedInteger('capacity')->nullable();
$table->boolean('is_active')->default(true);
$table->text('notes')->nullable();
$table->timestamps();
```

### 3.2 `create_lecture_schedules_table`
```php
$table->id();
$table->foreignId('course_id')->constrained()->cascadeOnDelete();
$table->foreignId('venue_id')->constrained()->restrictOnDelete();
$table->foreignId('year_id')->nullable()->constrained()->nullOnDelete(); // Q3
$table->string('day');                     // App\Enums\DayOfWeek: saturday..thursday
$table->time('start_time');
$table->time('end_time');
$table->timestamps();
$table->index(['venue_id', 'day', 'start_time', 'end_time']);   // تسريع فحص التعارض
$table->index(['course_id', 'day']);
```

### 3.3 `create_lecture_schedule_section_table` (pivot)
```php
$table->foreignId('lecture_schedule_id')->constrained()->cascadeOnDelete();
$table->foreignId('section_id')->constrained()->cascadeOnDelete();
$table->unique(['lecture_schedule_id', 'section_id']);
```

> **ملاحظة:** لا تعديل على أي جدول موجود — لا خطر على البيانات الحالية.

---

## 4. Enums جديدة (`app/Enums/`)

- **`VenueType`**: `Auditorium = 'auditorium'` (مدرج)، `Lab = 'lab'` (معمل)، `Classroom = 'classroom'` (قاعة دراسية)، `Other = 'other'` — مع `label()` عربية مثل نمط `Semester`.
- **`DayOfWeek`**: `Saturday..Thursday` مع `label()` (السبت...الخميس) و`order()` للفرز في الجدول الأسبوعي.

---

## 5. Models جديدة (`app/Models/`)

### `Venue`
- fillable: `name, type, capacity, is_active, notes`؛ casts: `type => VenueType`, `is_active => boolean`.
- `lectureSchedules(): HasMany`؛ `use HasDeletionGuards` مع `$blockingRelations = ['lectureSchedules']`.

### `LectureSchedule`
- fillable: `course_id, venue_id, year_id, day, start_time, end_time`.
- casts: `day => DayOfWeek`.
- العلاقات: `course()`, `venue()`, `year()`, `sections(): BelongsToMany (lecture_schedule_section)`.
- Methods مفيدة:
  - `durationInMinutes(): int`
  - `overlaps(self $other): bool` — نفس اليوم و `start < other.end && end > other.start`.
  - `selectedStudentsCount(): int` — `Section::whereIn('id', $this->sections)->withCount('students')`.

---

## 6. Services — قلب الخاصية

### `app/Services/LectureScheduleService.php` (جديد)

```php
class LectureScheduleService
{
    /** @param array<int, int> $sectionIds */
    public function validate(
        Course $course,
        Venue $venue,
        DayOfWeek $day,
        string $start,
        string $end,
        array $sectionIds,
        ?int $ignoreScheduleId = null,
    ): void; // يقذف LectureScheduleConflictException برسالة عربية جاهزة

    public function assertSectionsBelongToCourse(Course $course, array $sectionIds): void;   // BR-2
    public function assertVenueCapacity(Venue $venue, array $sectionIds): void;              // BR-3
    public function assertNoVenueConflict(Venue $venue, DayOfWeek $day, string $start, string $end, ?int $ignoreId): void;   // BR-4
    public function assertNoSectionConflict(array $sectionIds, DayOfWeek $day, string $start, string $end, ?int $ignoreId): void; // BR-5
    public function create(Course $course, array $attributes, array $sectionIds): LectureSchedule; // transaction + sync pivot
    public function update(LectureSchedule $schedule, array $attributes, array $sectionIds): void;
}
```

- فحوصات التعارض بـ**query واحد لكل نوع** (overlap condition) عبر `whereHas('sections')` — بدون N+1.
- رسالة الخطأ تتضمّن اسم الجلسة المتعارضة (المادة + المكان + اليوم + الوقت) — تُبنى من نفس الـquery بـ`with(['course','venue'])`.
- `Exception` مخصص `App\Exceptions\LectureScheduleConflictException` يُلتقط في Livewire ويتحول لـ`addError`/toast.

---

## 7. Controllers & Form Requests

### نمط الأماكن (يتبع `SectionController` الموجود حرفيًا)
- `app/Http/Controllers/Admin/VenueController.php` — resource `except(['show'])`: index/create/store/edit/update/destroy.
- `app/Http/Requests/Admin/StoreVenueRequest.php` + `UpdateVenueRequest.php`:
  - `name: required|string|max:255|unique:venues,name[,id for update]`
  - `type: required|in:(VenueType values)` — `capacity: nullable|integer|min:1`
  - رسائل عربية مثل `StoreSectionRequest`.
- Views: `resources/views/admin/pages/venue/{index,create,edit}.blade.php` — نسخ هيكل صفحة `section`.

### الجلسات → Livewire (تفاعلية: حساب السعة لحظيًا) — لا Controllers ولا Policies (تتبعًا للـconvention الموجود، الـauthorization بـpermissions فقط).

---

## 8. Livewire Components (`app/Livewire/Admin/LectureSchedule/`)

### `Index.php` + `index.blade.php` — route: `/lecture-schedules`
- فلاتر: التخصص → الفرقة → الترم → قائمة المواد (نفس نمط فلاتر `Course\Index`).
- اختيار مادة يعرض: جدول جلسات المادة (اليوم/الوقت/المكان/الشعب/إجمالي الطلاب) + أزرار Edit/Delete + زر "إضافة جلسة".
- `mount/edit` يفتح المودال/الصفحة الخاصة بـ`Form`.

### `Form.php` + `form.blade.php` — route: `/lecture-schedules/{course}/create` و`/{schedule}/edit`
- Properties: `venue_id, day, start_time, end_time, section_ids[], range_from, range_to`.
- `updatedSectionIds()` / methods `applyRange()`: حساب **لحظي** لـ`selectedStudentsCount` وعرضه مقابل `venue.capacity` (progress + لون أحمر عند التجاوز) — هذا هو الـreactivity المميز للـLivewire هنا.
- الشعب المعروضة = `$course->sections` فقط (BR-2 من جهة الواجهة) + عدد طلاب كل شعبة (`withCount('students')`).
- `save()`: `abort_unless(auth()->user()->can('lecture_schedules.create'), 403)` → validate الوقت (`end > start`) → استدعاء `LectureScheduleService::validate/create` → catch conflict exception → `dispatch('toast', type: 'danger')`.

### `WeekGrid.php` (خطوة اختيارية داخل نفس الـfeature) — عرض شبكة يوم × وقت لمادة أو لمكان.

---

## 9. Routes (`routes/web.php`)

```php
// Venue Routes (نفس نمط sections)
Route::resource('venues', VenueController::class)->except(['show'])
    ->middleware('permission:venues.view');

// Lecture Schedules Routes
Route::get('lecture-schedules', \App\Livewire\Admin\LectureSchedule\Index::class)
    ->name('lecture-schedules.index')->middleware('permission:lecture_schedules.view');
Route::get('lecture-schedules/{course}/create', \App\Livewire\Admin\LectureSchedule\Form::class)
    ->name('lecture-schedules.create')->middleware('permission:lecture_schedules.create');
Route::get('lecture-schedules/{schedule}/edit', \App\Livewire\Admin\LectureSchedule\Form::class)
    ->name('lecture-schedules.edit')->middleware('permission:lecture_schedules.edit');
```

## 10. Permissions & Sidebar

- `config/permissions.php` — إضافة:
```php
'venues' => ['label' => 'الأماكن والمدرجات', 'actions' => ['view'=>..., 'create'=>..., 'edit'=>..., 'delete'=>...]],
'lecture_schedules' => ['label' => 'جدول المحاضرات', 'actions' => [view/create/edit/delete]],
```
- تشغيل `php artisan db:seed --class=PermissionsSeeder` (idempotent — لا يحذف شيئًا).
- `resources/views/admin/layouts/sidebar.blade.php`: رابطان جديدين تحت مجموعة "المواد الدراسية" (بجوار `courses.index`) مع `@can`.

---

## 11. Integrations

- **لا Integrations خارجية** (لا APIs، لا packages جديدة).
- داخلية فقط: `students` (عدّاد السعة)، `course_section` (الشعب المؤهلة)، `RegistrationFee` (fallback)، `Year` (scoping)، `HasDeletionGuards`.
- مستقبلًا (خارج v1): صفحة الطالب، طباعة/PDF للجدول الأسبوعي، تصدير Excel.

---

## 12. Testing Strategy (Pest 4)

### Factories & Seeders
- `VenueFactory` (states: `auditorium()`, `lab()`), `LectureScheduleFactory` + `DatabaseFactories` registration.
- إضافة بلوك demo للـ`DemoDataSeeder` (أماكن + جلسات) لبيئة التجربة.

### Unit/Service — `tests/Feature/LectureSchedule/`
1. `LectureScheduleServiceTest`:
   - venue conflict: overlap مرفوض / **تلاصق 10:30→10:30 مقبول** / أوقات مختلفة في أيام مختلفة مقبولة.
   - section conflict عبر مادتين مختلفتين.
   - capacity: تجاوز مرفوض، `capacity=null` متخطي، شعبة صفر طلاب محسوبة.
   - sections من تخصص تاني مرفوضة (BR-2).
   - `ignoreScheduleId`: تعديل نفس الجلسة لا تتعارض مع نفسها.
2. **Feature/Livewire:**
   - `guest|بدون صلاحية → 403` لكل route.
   - إنشاء جلسة صحيحة → `assertDatabaseHas` للجدول + الـpivot + toast نجاح.
   - محاولة تعارض → رسالة عربية + لا سجل في الـDB.
   - range "من 1 إلى 10" يولّد الـsection_ids الصحيح ويطبّع `من > إلى`.
   - حذف مكان مرتبط → ممنوع (نمط `HasDeletionGuards`).
   - Venue CRUD tests مثل اختبارات `sections` الموجودة.
3. التشغيل: `php artisan test --compact` + `vendor/bin/pint --dirty --format agent` قبل التسليم.

---

## 13. Migration & Deployment Strategy

1. كل التغييرات **additive** (3 جداول جديدة + config + seed permissions) → deploy عادي بدون downtime:
   ```
   php artisan migrate --force
   php artisan db:seed --class=PermissionsSeeder --force
   php artisan optimize:clear   # sidebar/permissions cache
   ```
2. منح الصلاحيات الجديدة للأدوار المناسبة من شاشة `users` (لا تغيير في أدوار موجودة تلقائيًا).
3. **Rollback آمن:** `migrate:rollback` ينزل الجداول الجديدة فقط؛ لا بيانات قديمة تتأثر.
4. لا حاجة لأي data migration من السيستم القديم (`chs`) — شعبه محسوبة ديناميكيًا وغير قابلة للتحويل الآمن؛ الترحيل يكون إدخال أماكن وجداول يدويًا بعد التشغيل.

---

## 14. Dependencies

- **لا حزم جديدة إطلاقًا** — كل المطلوب موجود (Livewire, Spatie Permission, Tabler UI).
- لا تغيير في `composer.json` / `package.json`.

---

## 15. Risks & Mitigations

| # | الخطر | الاحتمال | التخفيف |
|---|---|---|---|
| R1 | التعارض الزمني يُحسب غلط (ثوانٍ/حدود متلاصقة) | متوسط | كل منطق الـoverlap في **Service واحد** + اختبارات حدود (10:30|10:30) قبل أي UI |
| R2 | غموض مصدر السعة (فعلي vs إعدادات RegistrationFee) — Q1 | عالي | حسم Q1 قبل البرمجة؛ التجريد في `assertVenueCapacity` يسمح بتبديل المصدر بسطر واحد |
| R3 | جلسات بلا scoping سنة/ترم تسبب تعارضات وهمية عبر السنين — Q3 | متوسط | `year_id` nullable من البداية + تضمينه في فحوصات التعارض |
| R4 | تغيير شعبة لمستواها/تخصصها بعد الجدولة يكسر BR-2 | منخفض | فحص إعادة التحقق عند update للجلسة + warning في شاشة الـIndex |
| R5 | قبول تجاوز السعة كتحذير فقط يخلق جداول غير واقعية — Q4 | متوسط | البدء برفض صارم؛ override لاحقًا بصلاحية منفصلة إن طُلب |
| R6 | تكرار إدخال الجلسات يدويًا لكل مادة مرهق | متوسط | range picker + (لاحقًا) "تكرار نفس الجلسة أسبوعيًا" هو افتراضي أصلًا؛ نسخ جلسات مادة لمادة أخرى كـ enhancement |
| R7 | أداء شبكة الأسبوع مع كثرة الجلسات | منخفض | الـcomposite index المخطط؛ حجم البيانات الجامعي صغير |

---

## 16. ترتيب التنفيذ المقترح (Build Order)

1. Migrations + Enums + Models + Factories.
2. `LectureScheduleService` + اختبارات الـService (خط الدفاع الأساسي).
3. Venue CRUD (Controller + Requests + Views + Permissions) + اختباراته.
4. Livewire `Index` + `Form` + الـrange picker + حساب السعة اللحظي + الاختبارات.
5. Routes + Sidebar + seed permissions + منح الأدوار.
6. Pint + `php artisan test --compact` + مراجعة يدوية على المتصفح.
