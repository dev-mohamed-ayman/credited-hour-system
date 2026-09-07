# جدول الامتحانات — خطة التنفيذ (How)

> **ملف مكمل لـ:** `Exam Scheduling — Feature Spec (What & Why).md`
> **المشروع:** `/Users/mohamedayman/Herd/D-Informatics/credited-hour-system`

---

## 1. فحص الـRepository — الجاهزية الحالية

نفس الـstack الموثّق في خطة المحاضرات (Laravel 12 · Livewire 3 · Tabler · Spatie Permission · Pest 4 · Pint · **لا Policies** — authorization بالـpermissions فقط). عناصر إضافية تخص الامتحانات:

| الموجود حاليًا | الدور في الخاصية الجديدة |
|---|---|
| `Registration` (student_id, year_id, semester, status=approved) + `RegistrationCourse` (course_id, grade_id) | **مصدر الحقيقة لجمهور الممتحنين (BR-2)** — البنية اللي السيستم القديم كان محروم منها |
| `Year::current()` / `Year::currentSemester()` / `Semester` enum | الفلترة الافتراضية للوحة + scoping الجلسات |
| `Student` (section_id, level_id, softDeletes) + `Section`/`Level`/`Department` | ترتيب التوزيع، فلاتر اللوحة، قاعدة رقم الفرقة في الجلوس (Q2) |
| `app/Livewire/Admin/StudentAffairs/SeatNumbers.php` | **نموذج مرجعي** للتوليد التسلسلي المستقر بالاسم داخل transaction |
| `StudentController::printSeatNumbers` + `resources/views/admin/pages/student/print_seat_number.blade.php` | نمط صفحات الطباعة (browser print بدون أي حزم PDF) |
| `config/permissions.php` + `PermissionsSeeder` (idempotent) | إضافة `exam_schedules` module |
| `HasDeletionGuards` | حماية حذف الكيانات المرتبطة |
| **لا يوجد** أي package لـExcel/CSV/PDF في `composer.json` | استيراد CSV يدوي بـPHP native (بدون إضافة حزم — قاعدة المشروع) |

### تحليل السيستم القديم (`chs`) — ما نأخذه وما نتجنبه
| الأثر القديم | الكود | القرار |
|---|---|---|
| `exam_table` CSV import | `StudentAffairsController::updateExamTime:2552` — تاريخ/وقت **نص أحرار** بلا نوع ولا فحص | ❌ anti-pattern → أعمدة `DATE`/`TIME` + validation كامل |
| `exam_place` CSV import | `updateExamPlaces:2479` — اللجنة نص، بلا كيان/سعة/مكان | ❌ → `exam_committees` كيان أول-class مرتبط بـ`venues` |
| قاعدة رقم الجلوس `digits:5 + starts_with:رقم الفرقة` | نفس السطر | ✅ فكرة جيدة → تتعمل كـformat rule قابلة للتهيئة في الـService (Q2) |
| عرض الطالب `getStudentExamTable` + `StudentHomePage` | `StudentTrait.php:473` — joins يدوية، ترتيب بالتاريخ، إظهار "باقٍ له" | ✅ الفكرة → Livewire بسيط فوق relations نظيفة + بوابة نشر |
| طباعة كروت اللجان | `print_student_seating_number_card.blade.php` | ✅ يعادلها كشف حضور لكل لجنة + جدول طالب |
| التسامح مع أخطاء الترويسة (`رقم اللجنه/اللجنة`) | نفس الـvalidator | ✅ يستمر في قارئ الـCSV |

---

## 2. الموديلات الموجودة وإيه اللي هنستخدمه

- **نستخدم كما هو:** `Course`, `Registration`, `RegistrationCourse`, `Student`, `Year`, `Section`, `Semester` enum, `HasDeletionGuards`.
- **`Venue`**: يُعاد استخدامه من feature المحاضرات؛ **لو اتعملت الامتحانات الأول**، نفس الـmigration الخاص بـ`venues` يدخل هنا (Q6 — dependency واحد مشترك بين الخطةين).
- **تعديلات طفيفة على موديلات موجودة:** إضافة `examSessions(): HasMany` لـ`Course` و`Year`، وإدراجهم في `$blockingRelations` (BR-10).

**موديلات جديدة:** `ExamSession`, `ExamCommittee`, `ExamSeatAssignment`.

---

## 3. Database Changes (3–4 migrations — additive بالكامل)

### 3.1 `create_exam_sessions_table`
```php
$table->id();
$table->foreignId('course_id')->constrained()->cascadeOnDelete();
$table->foreignId('year_id')->constrained()->cascadeOnDelete();
$table->string('semester');                          // App\Enums\Semester
$table->string('type')->default('regular');          // App\Enums\ExamType
$table->date('exam_date');
$table->time('start_time');
$table->time('end_time');
$table->string('status')->default('draft');          // App\Enums\ExamSessionStatus
$table->text('notes')->nullable();
$table->timestamps();
$table->unique(['course_id', 'year_id', 'semester', 'type']);   // BR-1
$table->index(['year_id', 'semester', 'exam_date']);
```

### 3.2 `create_exam_committees_table`
```php
$table->id();
$table->foreignId('exam_session_id')->constrained()->cascadeOnDelete();
$table->foreignId('venue_id')->constrained()->restrictOnDelete();
$table->string('name');                              // "لجنة 1" / "أ"
$table->unsignedInteger('capacity');
$table->timestamps();
$table->unique(['exam_session_id', 'name']);
```

### 3.3 `create_exam_seat_assignments_table`
```php
$table->id();
$table->foreignId('exam_session_id')->constrained()->cascadeOnDelete();
$table->foreignId('exam_committee_id')->constrained()->cascadeOnDelete();
$table->foreignId('student_id')->constrained()->cascadeOnDelete();
$table->string('seat_number');
$table->timestamps();
$table->unique(['exam_session_id', 'student_id']);   // طالب واحد = لجنة واحدة
$table->unique(['exam_committee_id', 'seat_number']); // BR-6 على مستوى الـDB
```

### 3.4 (اختياري حسب Q4) `add_exam_period_to_years_table`
```php
$table->date('first_semester_exam_from')->nullable(); // ×3 فصول دراسية
// أو جدول exam_periods مستقل لو احتجنا أكثر من نافذة/ترم
```

---

## 4. Enums جديدة (`app/Enums/`)

- **`ExamType`**: `Regular = 'regular'` (عادي)، `Resit = 'resit'` (فصل ثانٍ)، `Improvement = 'improvement'` (تحسين) — مع `label()`.
- **`ExamSessionStatus`**: `Draft` (مسودة) / `Published` (منشور) — نمط `MilitaryEducationCourseStatus`.

---

## 5. Models جديدة (`app/Models/`)

### `ExamSession`
- casts: `semester => Semester`, `type => ExamType`, `status => ExamSessionStatus`, `exam_date => date:Y-m-d`.
- العلاقات: `course()`, `year()`, `committees(): HasMany`, `seatAssignments(): HasMany`، `registeredStudents()` (query من BR-2 — انظر الـService).
- `use HasDeletionGuards` مع `$blockingRelations = ['committees', 'seatAssignments']`.
- `overlaps(ExamSession $other): bool` — نفس اليوم و`start < other.end && end > other.start`.

### `ExamCommittee`
- `examSession()`, `venue()`, `assignments(): HasMany`، `assignedCount()`، `isFull(): bool`.

### `ExamSeatAssignment`
- `examSession()`, `committee()`, `student()` — جدول خفيف بلا business logic.

---

## 6. Services — قلب الخاصية

### `app/Services/ExamScheduleService.php`
```php
class ExamScheduleService
{
    /** @return \Illuminate\Database\Eloquent\Collection<int, Student> جمهور الممتحنين (BR-2) */
    public function examinees(ExamSession $session): Collection;

    public function validateSession(ExamSession $session): void;
    // BR-7 (time/date) + BR-4 venue overlap + BR-3 student overlap

    /** @return Collection<int, StudentConflict> طلاب عندهم تداخل مع جلسات أخرى */
    public function findStudentConflicts(ExamSession $session): Collection;
    // query واحد: students في examinees($session) ليهم registration معتمدة
    // في جلسات تانية نفس (year, semester) بنفس التاريخ وتداخل الوقت

    public function assertPublishable(ExamSession $session): void;
    // examinees > 0 + seating up-to-date + صفر تعارضات + السعة (BR-5)

    public function publish(ExamSession $session): void;   // transaction + status
    public function unpublish(ExamSession $session): void; // يرجع draft ويخفي فورًا (BR-8)
}
```

### `app/Services/ExamSeatingService.php`
```php
class ExamSeatingService
{
    /** توليد تلقائي: تقسيم مستقر بالاسم (نفس روح SeatNumbers::generate) */
    public function autoAssign(ExamSession $session): void;
    // delete old assignments + chunk examinees per committee capacity
    // seat_number من schema Q2 (افتراضي: تسلسلي داخل اللجنة)

    /** @throws ExamSeatingImportException بأخطاء صف-بصف */
    public function importFromCsv(ExamSession $session, string $filePath): void;
    // صيغة القديم: [كود الطالب، رقم اللجنة، رقم الجلوس] + تطبيع BOM/windows-1256
    // + قبول "اللجنه/اللجنة" + كله-أو-لا-شيء (BR-9)

    public function isSeatingStale(ExamSession $session): bool; // BR-13: compare examinees vs assignments
}
```

- كل رسائل الأخطاء عربية جاهزة للعرض (نفس أسلوب `CoursePrerequisiteValidator`).
- فحص BR-3 هو **القيمة المضافة الكبرى** على القديم — لازم يكون query واحد بدون N+1 (join `registrations` + `registration_courses`).

---

## 7. Controllers & Form Requests

- **Livewire للكل** (الشاشات تفاعلية: فلاتر، repeater لجان، معاينة توزيع) — لا Form Requests تقليدية؛ الـvalidation في `rules()` داخل كل component زي `CourseForm`.
- **Controller واحد للطباعة:** `app/Http/Controllers/Admin/ExamPrintController.php`:
  - `committeeSheet(ExamCommittee $committee)` → `admin/pages/exam/committee-sheet.blade.php` (كشف حضور: رقم جلوس/كود/اسم/شعبة + خانات توقيع) — هيكل `print_seat_number.blade.php`.
  - `studentSchedule(Student $student)` → جدول الطالب المنشور للطباعة.

---

## 8. Livewire Components

### Admin (`app/Livewire/Admin/ExamSchedule/`)
| Component | الوظيفة |
|---|---|
| `Index.php` | اختيار سنة+ترم (افتراضي `Year::current()`) → جدول المواد ذات التسجيلات المعتمدة: [المادة، التخصص، الفرقة، عدد الممتحنين، التاريخ، الوقت، الحالة] + أزرار (تحديد/توزيع/نشر/حذف). الفلاتر تخصص/فرقة/حالة. |
| `Form.php` | إنشاء/تعديل جلسة: course (مقفول عند التعديل)، type، date، start/end، ملاحظات + **repeater اللجان** (venue_id من `venues` النشطة، name، capacity) + عدّاد لحظي: إجمالي السعة مقابل عدد الممتحنين (reactivity زي خطة المحاضرات). `save()` → `ExamScheduleService::validateSession` → catch → toast. |
| `Seating.php` | شاشة التوزيع: tabs لكل لجنة + جدول assignments، زر "توليد تلقائي"، رفع CSV (Livewire `WithFileUploads`, `temporaryFile`) + عرض أخطاء الاستيراد صفا بصف، شارة "توزيع غير محدّث" (BR-13). |

### Student (`app/Livewire/Student/ExamSchedule.php` أو بلوك داخل `Dashboard.php`)
- جدول امتحانات الطالب **المنشورة فقط** للسنة/الترم الحالي: اليوم (اسم عربي من `Carbon`)، التاريخ، الميعاد، المكان، اللجنة، رقم الجلوس — مرتب زمنيًا، والمواد بلا جلسة تظهر "لم يُحدد بعد" (سلوك القديم المفيد).
- زر طباعة → `exam.student-schedule.print`.

كل الـactions: `abort_unless(auth()->user()->can('exam_schedules.*'), 403)` — نمط `YearSettings::updateSemester`.

---

## 9. Routes (`routes/web.php`)

```php
// Exam Schedule Routes
Route::get('exam-schedules', \App\Livewire\Admin\ExamSchedule\Index::class)
    ->name('exam-schedules.index')->middleware('permission:exam_schedules.view');
Route::get('exam-schedules/{course}/create', \App\Livewire\Admin\ExamSchedule\Form::class)
    ->name('exam-schedules.create')->middleware('permission:exam_schedules.create');
Route::get('exam-schedules/{session}/edit', \App\Livewire\Admin\ExamSchedule\Form::class)
    ->name('exam-schedules.edit')->middleware('permission:exam_schedules.edit');
Route::get('exam-schedules/{session}/seating', \App\Livewire\Admin\ExamSchedule\Seating::class)
    ->name('exam-schedules.seating')->middleware('permission:exam_schedules.edit');
Route::get('exam-schedules/print/committee/{committee}', [ExamPrintController::class, 'committeeSheet'])
    ->name('exam-schedules.print.committee')->middleware('permission:exam_schedules.view');
Route::get('students/{student}/exam-schedule/print', [ExamPrintController::class, 'studentSchedule'])
    ->name('exam-schedules.print.student')->middleware('permission:students.view');
```
+ route مجموعة الطالب داخل `student` prefix الموجود.

## 10. Permissions & Sidebar

```php
// config/permissions.php
'exam_schedules' => [
    'label' => 'جدول الامتحانات',
    'actions' => ['view' => 'عرض', 'create' => 'إنشاء', 'edit' => 'تعديل',
                  'delete' => 'حذف', 'publish' => 'نشر'],
],
```
- `php artisan db:seed --class=PermissionsSeeder --force` (idempotent).
- Sidebar: مجموعة جديدة **"الامتحانات"** (أيقونة `tabler-calendar-clock`) أو إدراج تحت "المواد الدراسية" — مع `@can('exam_schedules.view')`.

---

## 11. Integrations

- **داخلية:** `Registration/RegistrationCourse` (الجمهور)، `Venue` (مشترك مع feature المحاضرات — راجع Q6)، `Year` (scoping + نافذة الامتحانات Q4)، طباعة Blade موجودة.
- **ترحيل من `chs` (اختياري حسب Q1):** أمر console لمرة واحدة `php artisan exams:import-legacy {csv}` يقرأ صيغة القديم ويحوّلها لـ`ExamSeatAssignment` — **command مش seeder** عشان تشغيل يدوي محسوب.
- **لا Integrations خارجية ولا حزم جديدة.**

---

## 12. Testing Strategy (Pest 4)

### Factories
- `ExamSessionFactory` (states: `published()`, `resit()`)، `ExamCommitteeFactory`، `ExamSeatAssignmentFactory` + توسيع `DemoDataSeeder` بسيناريو ترم كامل.

### Service tests — `tests/Feature/ExamSchedule/`
1. `ExamScheduleServiceTest`:
   - examinees = approved فقط (pending/rejected/cancelled مستثنون).
   - student conflict: تداخل مرفوض / تلاصق 11:00|11:00 مقبول / أيام مختلفة مقبول / ترمان مختلفان مستقلان.
   - venue conflict + استثناء السجل نفسه عند التعديل.
   - publish gates: 0 ممتحنين، seating stale، تعارض غير محلول، سعة ناقصة.
2. `ExamSeatingServiceTest`:
   - autoAssign توزيع مستقر (نفس المدخلات ⇒ نفس الأرقام — BR-12) واحترام السعة.
   - importFromCsv: ملف سليم، كود غير معروف، تكرار رقم جلوس، تكرار طالب، ترويسة "اللجنه"، BOM — وكلها كله-أو-لا-شيء.
3. **Livewire feature tests:** 403 لكل صلاحية، إنشاء/تعديل/نشر، ظهور toast التعارض، الطالب لا يرى إلا published، إعادة التوليد بعد تسجيل جديد.
4. **Print smoke tests:** كشف اللجنة يعرض أسماء الممتحنين؛ جدول الطالب فارغ قبل النشر.
5. التشغيل: `php artisan test --compact` + `vendor/bin/pint --dirty --format agent`.

---

## 13. Migration & Deployment Strategy

1. كل التغييرات **additive** (3–4 جداول + enums + config + permissions) → deploy بدون downtime:
   ```
   php artisan migrate --force
   php artisan db:seed --class=PermissionsSeeder --force
   php artisan optimize:clear
   ```
2. **Launch آمن:** الصلاحيات الجديدة لا تُمنح لأي دور تلقائيًا → الخاصية غير مرئية حتى التفعيل؛ الجدول لا يظهر للطلاب قبل أول `publish` فعلي (BR-8).
3. ترحيل بيانات القديم (لو مطلوب): تشغيل `exams:import-legacy` في sandbox أولًا ثم production يدويًا — لا يُنفذ من الـdeploy script.
4. **Rollback:** `migrate:rollback` ينزل جداول الامتحانات فقط؛ لا تأثير على التسجيلات/الدرجات.
5. الترتيب مع feature المحاضرات: إن لزم، `venues` migration يُنفَّذ مرة واحدة في أول feature يصل للـproduction (ثابت في إحداهما بـ`Schema::hasTable` guard أو وحدة migrations مشتركة).

---

## 14. Dependencies

- **لا حزم جديدة**: CSV بـPHP native (`fgetcsv` + تطبيع ترميز) — قرار متعمد لأن `composer.json` خالي من أي package Excel/CSV/PDF وقاعدة المشروع تمنع إضافة اعتماديات بدون موافقة. لو المطلوب دعم `.xlsx` حقيقي → نطلب موافقة على `maatwebsite/excel` (القديم كان يستخدمه).
- **Dependency وظيفي واحد:** جدول `venues` (مشترك مع جدول المحاضرات) — محسوم في Q6.

---

## 15. Risks & Mitigations

| # | الخطر | الاحتمال | التخفيف |
|---|---|---|---|
| R1 | فحص تعارض الطلاب (BR-3) غالي/بطيء مع آلاف الطلاب × عشرات المواد | متوسط | query واحد بـjoin عبر `registration_courses` + فهارس مقترحة؛ القوائم تُعرض paginated |
| R2 | تعارض التوقيت مع feature المحاضرات على جدول `venues` (نفس الـmigration مرتين) | عالي | حسم أي feature يُدمج أولًا + migration مشترك واحد فقط |
| R3 | غموض تنسيق رقم الجلوس (Q2) — قاعدة القديم قد لا تنطبق مع شعب النظام الجديد | عالي | التوليد في `ExamSeatingService` خلف interface format بسيط؛ حسم Q2 قبل الكود |
| R4 | استيراد CSV بترميز/أعمدة مختلفة عن المتوقع يفشل صامتًا | متوسط | تطبيع ترميز + قبول بدائل الترويسة + أخطاء صفيحة + كله-أو-لا-شيء |
| R5 | نشر جدول ثم تعديله يربك الطلاب (نسخ قديمة مطبوعة) | متوسط | BR-8 (رجوع تلقائي لـdraft) + طابع "آخر تحديث" على صفحة الطالب |
| R6 | تسجيلات متأخرة بعد التوزيع (BR-13) تُنسى | متوسط | شارة stale ملونة في الـIndex + منع publish تلقائي |
| R7 | أنواع الامتحانات (resit/improvement) تتطلب قواعد إضافية (مواد باقية للطالب) | منخفض | enum موجود من البداية؛ المنطق يُضاف عند اعتماد Q3 دون تغيير schema |

---

## 16. ترتيب التنفيذ المقترح (Build Order)

1. Migrations + Enums + Models + Factories (بما فيها `venues` لو لسه ما اتعملتش).
2. `ExamScheduleService` (examinees + conflicts + publish gates) + اختبارات الـService — **قبل أي UI**.
3. `ExamSeatingService` (autoAssign + importFromCsv) + اختبارات التوليد المستقر والاستيراد.
4. Livewire `Index` + `Form` (لوحات المواد والجلسات) + الصلاحيات والـroutes والـsidebar.
5. Livewire `Seating` + كشوف الطباعة.
6. صفحة/بلوك الطالب + بوابة النشر.
7. Pint + `php artisan test --compact` + سيناريو demo كامل في `DemoDataSeeder` ومراجعة يدوية.
