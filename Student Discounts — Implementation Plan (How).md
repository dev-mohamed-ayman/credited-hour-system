# خصومات الطلاب — خطة التنفيذ (How)

> **ملف مكمل لـ:** `Student Discounts — Feature Spec (What & Why).md`
> **المشروع:** `/Users/mohamedayman/Herd/D-Informatics/credited-hour-system`
> **الحالة:** ✅ قرارات معتمدة (Q1 سحب جزئي — Q3 صفر بتأكيد يدوي بلا وزاري ويُحسب سدادًا كاملًا — Q5 رصيد الهدية عبر WalletService فقط)

---

## 1. فحص الـRepository — الجاهزية الحالية

نفس الـstack الموثّق في الخطط السابقة (Laravel 12 · Livewire 3 · Tabler · Spatie Permission · Pest 4 · Pint · authorization بالـpermissions فقط بدون Policies). عناصر تخص المالية:

| الموجود حاليًا | الدور في الخاصية الجديدة |
|---|---|
| `StudentFeeTicket` (ticket_number, fee_type/fee_id, `amount`, status pending/paid/cancelled, year/semester/department/level/section, **`fee_details` JSON snapshot**) | الحافظة المستهدفة بالتعديل — الـsnapshot الموجود يحمي من آثار تعديل الرسوم لاحقًا (BR-5 مبنية عليه) |
| `app/Livewire/Admin/Finance/FeeIssuance.php::generateTickets()` (سطر 223+) | **نقطة التطبيق الأولى**: داخل الـtransaction الحالي للـcreate |
| `app/Livewire/Admin/Finance/FeePayment.php::confirmPayment()` | **نقطة العرض الثانية**: إظهار أصلي/خصم/صافي + تخطي الرقم الوزاري للـ0 (Q3) |
| `WalletService` (deposit/withdraw/refund + transactions) | **Q5 محسوم:** مسار "رصيد الهدية" البديل عن نوع 'محفظة' القديم (BR-10) — يُستخدم `deposit()` مباشرة ولا يمتد للخصومات؛ لا `StudentDiscount` لنوع محفظة |
| `RegistrationBillingService` (fee gate + settle) | خارج النطاق v1 (Q6) — الخصومات لا تدخل تسوية الساعات |
| `DailyPaymentDateTime` + `Setting::ministerial_receipt_*` | بوابة يوم السداد وأرقام الإيصالات — الخصم ما يعطّلش المنطقين |
| `StudentFinancialStatus` / `DailyPayments` / `print-tickets` views | شاشات التقارير اللي هتضيف عمود الخصم |
| `finance` permissions module (`config/permissions.php:72`) | يُضاف بجانبه `discounts` module |
| `HasDeletionGuards`, Enums بـ`label()` عربية, Form Requests/Livewire `rules()` | نفس الأنماط المتبعة |

### السيستم القديم (`chs`) — الدروس المستفادة (تفصيلي)
| الأثر | المكان | القرار |
|---|---|---|
| جدول `students_discounts` خام بلا موديل + 6 دوال حساب متكررة في `FinanceTrait` (`getTotalStudyDiscount:112`, `getTotalOtherDiscount:148`, `getTotalWalletDiscount:158`, `getTotalPayment:45`...) | كل شاشة مالية تعيد الحساب | ❌ → **الحافظة تخزن أرقامها النهائية** (`original/discount/net`)؛ التقارير تقرأ من الحافظة فقط |
| إضافة الخصم تحذف الحوافظ pending وترجّع المدفوع للمحفظة | `storeDiscount:1355-1385` | ❌ → BR-7: إعادة تسعير صريحة بزر + سجل، ولا رجّع تلقائي |
| الخصم "بيتحسب مدفوع" بحساب add-then-subtract داخل `payTicket:358/402` | arithmetic مكرر | ❌ → الخصم يُطبَّق وقت **الإصدار** لا وقت الدفع؛ الدفع على الصافي مباشرة |
| نوع 'محفظة' = إيداع رصيد مش خصم | `storeDiscount` فرع محفظة | ❌ → `WalletService::deposit` (Q5) |
| نوع 'ادارية' = إعفاء مشروط بالمساواة التامة + `payment_type='Excep'` | نفس الدالة | ⚠️ الفكرة (إعفاء كامل) تبقى **سلوك** خصم ≥ قيمة الرسم، مش قيد تحقق |
| فرع 'خدمات تعليمية' `dd("here")` + متغير غير معرّف | سطر 1467 | ❌ كود ميت — لا يُرحَّل |
| CSV [كود/نوع/قيمة/سبب] بتسامح ترويسات إملائي | `storeDiscount` EXCEL block | ✅ الفكرة في FR-6 مع صرامة نظيفة (كله-أو-لا-شيء + أخطاء صفيحة) |
| `deleteDiscount` يشترط مفيش حافظة مستخدمة | سطر 1676 | ✅ تتحول لـ**Revoke** بحالات BR-2/BR-9 بدل حذف |
| طباعة `print_ticket` تعرض المستحق/الخصومات/المسحوب/الصافي | blade | ✅ نفس شكل الإيصال في `print-tickets` الحالية |

---

## 2. الموديلات الموجودة وإيه اللي هنستخدمه

- **نستخدم كما هو:** `StudentFeeTicket`, `Student`, `RegistrationFee`, `AdditionalFee`, `FeeTemplate`, `Year`, `Semester`, `WalletService`, `DailyPaymentDateTime`.
- **نمدّد:** `Student` → `hasMany discounts()`؛ `StudentFeeTicket` → `discountUsages()` + casts جديدة.
- **موديلات جديدة:** `StudentDiscount`, `StudentDiscountUsage`.

---

## 3. Database Changes

### 3.1 `create_student_discounts_table`
```php
$table->id();
$table->foreignId('student_id')->constrained()->cascadeOnDelete();
$table->string('scope');                       // App\Enums\DiscountScope: registration|additional|any
$table->unsignedBigInteger('fee_id')->nullable(); // رسم محدد (اختياري، بدون FK صارم مثل StudentFeeTicket)
$table->foreignId('year_id')->nullable()->constrained()->nullOnDelete();
$table->string('semester')->nullable();        // App\Enums\Semester — null = كل الأتراب
$table->string('mode');                        // App\Enums\DiscountMode: fixed|percentage
$table->decimal('value', 10, 2);               // مبلغ أو نسبة حسب mode
$table->decimal('remaining_amount', 10, 2)->nullable(); // للـfixed: الرصيد المتبقي (Q1)
$table->string('status')->default('active');   // App\Enums\DiscountStatus
$table->string('reason');
$table->string('decision_number')->nullable(); // رقم القرار
$table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
$table->timestamp('expires_at')->nullable();
$table->string('revoked_reason')->nullable();
$table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
$table->timestamp('revoked_at')->nullable();
$table->timestamps();
$table->index(['student_id', 'status', 'scope']);
```

### 3.2 `create_student_discount_usages_table` (سجل التطبيق — التدقيق المالي)
```php
$table->id();
$table->foreignId('student_discount_id')->constrained()->cascadeOnDelete();
$table->foreignId('student_fee_ticket_id')->constrained()->cascadeOnDelete();
$table->decimal('applied_amount', 10, 2);
$table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();
$table->timestamps();
$table->unique(['student_discount_id', 'student_fee_ticket_id']); // BR-4 على مستوى الـDB
```

### 3.3 `add_discount_columns_to_student_fee_tickets_table`
```php
$table->decimal('original_amount', 10, 2)->nullable()->after('amount'); // null = بلا خصم
$table->decimal('discount_amount', 10, 2)->default(0)->after('original_amount');
```
> `amount` يفضل **الصافي** — كل الاستعلامات الحالية (`outstandingTotal`, fee gate, reports) تفضل شغالنة بدون تعديل. الحوافظ القديمة `original_amount = null` ⇒ تعامل كـ`amount + 0`.

---

## 4. Enums جديدة (`app/Enums/`)

- **`DiscountScope`**: `Registration='registration'` (رسوم التسجيل)، `Additional='additional'` (رسوم إضافية)، `Any='any'` — مع `label()`.
- **`DiscountMode`**: `Fixed='fixed'` (مبلغ ج.م)، `Percentage='percentage'` (٪).
- **`DiscountStatus`**: `Active`, `PartiallyApplied`, `Exhausted`, `Revoked` — مع `label()` عربية.

---

## 5. Models جديدة (`app/Models/`)

### `StudentDiscount`
- casts: `mode/status => enums`, `value/remaining_amount => 'decimal:2'`, `expires_at => datetime`.
- دوال مساعدة: `isExpired(): bool`, `remainingValue(): ?float`, `appliesTo(string $feeType, ?int $yearId, ?Semester $semester, ?int $feeId): bool`.
- العلاقات: `student()`, `usages(): HasMany`, `creator()`.
- **بلا `HasDeletionGuards`** — الحذف ممنوع أصلًا؛ الإلغاء هو المسار (BR-2).

### `StudentDiscountUsage`
- `discount()`, `ticket()`, `appliedBy()` — جدول أثر فقط.

---

## 6. Services — قلب الخاصية

### `app/Services/DiscountService.php` (جديد)
```php
class DiscountService
{
    /** الخصومات المؤهلة لطالب/nطاق لحظة الإصدار (active + غير منتهية + في النطاق) */
    public function eligibleFor(
        Student $student, string $feeType, ?int $feeId = null,
        ?int $yearId = null, ?Semester $semester = null,
    ): Collection;

    /**
     * يحسب التوزيع النهائي: [discount_id => applied_amount] مع احترام BR-3
     * (الأقدم أولًا، لا يتجاوز الصافي الأصلي)
     * @return array{applied: array<int,float>, net: float}
     */
    public function planApplication(Collection $discounts, float $originalAmount): array;

    /** يطبّق على حافظة جديدة/قائمة — transaction + lockForUpdate على الخصم (BR-4) */
    public function applyToTicket(StudentFeeTicket $ticket, array $applied, User $performedBy): void;
    // داخله: original/discount/amount على الحافظة + usages + تحديث remaining/status الخصم

    public function revoke(StudentDiscount $discount, string $reason, User $user): void;
    // BR-9: يرفض لو فيه usage على حافظة paid؛ يُلغي المتبقي فقط للمطبّق جزئيًا

    public function applyToPendingTicket(StudentDiscount $discount, StudentFeeTicket $ticket, User $user): void;
    // BR-7: يعيد تسعير حافظة pending + usage + تغيير amount
}
```

- **قفل الصّف**: `StudentDiscount::whereKey($id)->lockForUpdate()->first()` داخل كل transaction تطبيق — يمنع السباق (Edge 7).
- كل الأرقام `bcadd/bcsub` أو round بـ`decimal:2` — لا floats.
- الاستهلاك من `remaining_amount` للـfixed؛ الـpercentage ما بيستهلكش رصيد (Q2).

---

## 7. Controllers & Form Requests

- **Livewire لشاشة الخصومات** (تفاعلية: بحث طالب، live preview للحافظات المؤهلة) — لا Form Requests جديدة؛ `rules()` داخل الـcomponent زي `CourseForm`.
- **Controller طباعة**: لا جديد — `print-tickets` الحالية تتغذى من أعمدة الحافظة الجديدة.

---

## 8. Livewire Components

### `app/Livewire/Admin/Finance/Discounts/Index.php` + `index.blade.php` (جديد)
- فلاتر: بحث طالب (اسم/كود)، حالة، نطاق، سنة/ترم.
- جدول الخصومات + شارة الحالة + "المطبَّق/المتبقي".
- Modal إضافة: mode (مبلغ/نسبة)، value، scope (+ رسم محدد لو additional)، year/semester (اختياري)، reason، decision_number، expires_at.
- أزرار: Revoke (بسبب إلزامي) + "تطبيق على الحافظة القائمة" (يظهر لو فيه pending مؤهلة — BR-7).
- Import CSV (FR-6): `WithFileUploads` + نفس قواعد BR-9/Edge 12.
- كل الـactions: `abort_unless(auth()->user()->can('discounts.*'), 403)`.

### تعديل `FeeIssuance.php` (موجود — تغييرات محدودة)
- بعد `loadFees()`: استدعاء `DiscountService::eligibleFor` لكل رسم، وعرض شارة "خصم متاح" + الصافي المتوقع لكل بند (reactive).
- داخل `generateTickets()` transaction: `planApplication` → `applyToTicket` بعد كل `StudentFeeTicket::create` — **نفس الـtransaction** ⇒ لا حافظة بغير خصمها.
- ملخص قبل التوليد: إجمالي أصلي / إجمالي خصومات / صافي.

### تعديل `FeePayment.php` (موجود)
- عرض `original_amount` + `discount_amount` + `amount` بدل `amount` لوحدها.
- **حافظة الصافي 0 (Q3 محسوم):**
  - تخطي فحص `ministerial_receipt_end` عندما تكون كل الحوافظ المحددة صفرية — لا يُستهلك رقم وزاري.
  - **تأكيد يدوي** من الصندوق يسجّل `status=paid` + `paid_at` + ملاحظة "خصم كامل" (لا إقفال تلقائي).
  - **⚠️ BR-13 حرج:** بعد التأكيد يجب أن تُستبعد الحافظة من `RegistrationBillingService::outstandingTotal/outstandingTickets/hasOutstandingFees/checkFeeGate` (كلها تعتمد `scopeUnpaid` = `status='pending'` ⇒ حافظة `paid` تستبعد تلقائيًا حتى لو `amount=0`) — **لكن** انتبه لسطر `FeePayment.php:214` الذي يودع `ticket->amount` في المحفظة؛ عند الصفر **تخطَّ الإيداع** (`if ($ticket->amount > 0)`) حتى لا يُنشئ `WalletTransaction` بقيمة 0.
  - اختبار يثبت أن `checkFeeGate` يمر بعد سداد حافظة صفرية (لا يحجب تسجيل المواد).

### تعديل `StudentFinancialStatus.php` + `DailyPayments` + `print-tickets.blade.php`
- أعمدة/سطور الخصومات (تُقرأ من الحافظة مباشرة — BR-6).

---

## 9. Routes (`routes/web.php`)

```php
Route::get('finance/discounts', \App\Livewire\Admin\Finance\Discounts\Index::class)
    ->name('admin.finance.discounts')->middleware('permission:discounts.view');
```

## 10. Permissions & Sidebar

```php
// config/permissions.php
'discounts' => [
    'label' => 'خصومات الطلاب',
    'actions' => ['view' => 'عرض', 'create' => 'إنشاء', 'edit' => 'تعديل', 'revoke' => 'إلغاء'],
],
```
- `php artisan db:seed --class=PermissionsSeeder --force` (idempotent).
- Sidebar: رابط "خصومات الطلاب" داخل مجموعة المالية (بجوار fee-issuance) مع `@can`.

---

## 11. Integrations

- **داخلية فقط:** `StudentFeeTicket` (التطبيق)، `WalletService` (بديل نوع محفظة القديم — سطر توثيق في الـUI مش كود)، `Setting` (المدى الوزاري عند الصفر)، `User` (created_by/applied_by).
- **ترحيل اختياري (FR-7):** `php artisan discounts:import-legacy` يقرأ `students_discounts` القديمة (في DB القديم) ويحوّل `'دراسية'→scope=registration`، `'اخرى'→additional`، `'ادارية'→fixed على الرسم الإداري`، ويتجاهل 'خدمات تعليمية' (كود ميت) — command يدوي مش seeder. **نوع 'محفظة' (Q5 محسوم):** لا يُرحَّل كخصم إطلاقًا؛ يمر عبر `WalletService::deposit` كإيداع رصيد (BR-10).
- **لا حزم جديدة** (CSV بـPHP native مثل قرار خطة الامتحانات).

---

## 12. Testing Strategy (Pest 4)

### Factories
- `StudentDiscountFactory` (states: `fixed()`, `percentage()`, `scoped($feeType)`, `expired()`, `revoked()`) + توسيع `DemoDataSeeder` (طالب بخصم + حافظة مطبَّقة).

### Service tests — `tests/Feature/Discounts/`
1. `DiscountServiceTest`:
   - eligibleFor: نطاق/سنة/ترم/scope صحيحين؛ revoked/expired مستثنيان.
   - planApplication: تطبيق كامل، **جزئي (سحب Q1)**، خصمان على حافظة (الأقدم أولًا)، سقف القيمة الأصلية (BR-3)، percentage rounding.
   - applyToTicket: الحافظة تخزن original/discount/net؛ usages تُسجَّل؛ remaining/status الخصم يتحدثوا.
   - **التزامن (BR-4):** عمليتان متزامنتان على نفس الخصم ⇒ إحداهما فقط تنجح (استخدم `DB::transaction` متداخلين في اختبار بـ`lockForUpdate` أو محاكاة قراءة-قفل-كتابة).
   - revoke: مرفوض لو usage على حافظة paid؛ مسموح للمتبقي جزئيًا.
   - applyToPendingTicket: إعادة تسعير pending + لا تلمس paid.
2. **Livewire feature tests:**
   - `FeeIssuance`: إصدار مع خصم ⇒ `assertDatabaseHas` بالقيم الثلاث + toast؛ بدون خصم ⇒ `discount_amount=0` و`original_amount=null` (لا انحدار سلوكي).
   - `FeePayment`: عرض الصافي؛ **حافظة 0 (Q3):** تتأكد يدويًا بدون استهلاك رقم وزاري، ولا تُنشئ `WalletTransaction` بقيمة 0 (تخطي إيداع المحفظة).
   - **BR-13 regression:** بعد سداد حافظة صافيها 0 ⇒ `checkFeeGate` يمر و`outstandingTotal` لا يضمها (لا يحجب تسجيل المواد).
   - `Discounts\Index`: 403 لكل صلاحية؛ إنشاء/إلغاء؛ استيراد CSV سليم/فاشل (كله-أو-لا-شيء).
3. **Regression:** `outstandingTotal`/fee gate في `RegistrationBillingService` لسه يشتغلوا على الحوافظ القديمة (null original_amount).
4. التشغيل: `php artisan test --compact` + `vendor/bin/pint --dirty --format agent`.

---

## 13. Migration & Deployment Strategy

1. additive + **عمودان nullable/default على جدول موجود** (`student_fee_tickets`) — آمن تمامًا؛ لا backfill مطلوب (null = بلا خصم).
   ```
   php artisan migrate --force
   php artisan db:seed --class=PermissionsSeeder --force
   php artisan optimize:clear
   ```
2. **Launch آمن:** صلاحية `discounts.*` لا تُمنح تلقائيًا؛ حتى بدون منح، كل الحوافظ الجديدة `discount_amount=0` ⇒ لا تغيير في سلوك المالية القائم.
3. الترحيل القديم (لو Q5/FR-7 معتمدين): تشغيل يدوي في sandbox أولًا.
4. **Rollback:** `migrate:rollback` ينزل الجداول + العمودين؛ الحوافظ المتأثرة بخصومات مطبَّقة تحتاج مراجعة قبل الـrollback (نادر — الخاصية جديدة).

---

## 14. Dependencies

- **لا حزم جديدة.** CSV native؛ الطباعة Blade موجودة؛ كل الحسابات على أعمدة `decimal`.
- لا dependency على features تانية (المحاضرات/الامتحانات) — مستقل تمامًا.

---

## 15. Risks & Mitigations

| # | الخطر | الاحتمال | التخفيف |
|---|---|---|---|
| R1 | أخطاء تقريب/float في المال (نسبة 33.33%) | متوسط | `decimal:2` casts + round مركزي في `planApplication` + اختبارات حدود |
| R2 | إنفاق مزدوج للخصم تحت التزامن (نفس ثغرة القديمة الحسابية) | متوسط | `lockForUpdate` + `unique(discount,ticket)` + اختبار تزامن |
| R3 | كسر تقارير مالية قائمة تقرأ `amount` | منخفض | `amount` يفضل الصافي؛ أي تقرير يحتاج الإجمالي يضيف `original_amount ?? amount` — مراجعة `DailyPayments`/`StudentFinancialStatus` في PR |
| R4 | حوافظ pending قديمة تتفاجأ بخصومات (سلوك القديم كان حذفها) | متوسط | BR-7 صريح: لا تطبيق تلقائي على pending؛ زر إعادة تسعير يدوي مسجَّل |
| R5 | خلط المستخدم بين "خصم" و"إيداع محفظة" (تراث القديم) | متوسط | BR-10 + تلميح في UI الشاشة الجديدة + توثيق الفرق في الـspec |
| R6 | تسامح CSV القديم مع الأخطاء يولّد عادات رديئة عند الاستيراد | متوسط | استيراد صارم كله-أو-لا-شيء + تقرير أخطاء صفيحة |
| R7 | حافظة صافيها 0 تُقفل تلقائيًا بدون مراجعة Cashier | منخفض | **محسوم (Q3):** تأكيد يدوي بدون رقم وزاري يبقي أثرًا بشريًا؛ **مع الانتباه لسطر الإيداع في `FeePayment.php:214`** (تخطي إيداع المحفظة عند الصفر) + اختبار BR-13 أن `checkFeeGate` يمر |

---

## 16. ترتيب التنفيذ المقترح (Build Order)

1. Migrations + Enums + Models + Factories.
2. `DiscountService` (eligible/plan/apply/revoke) + اختبارات الـService — **قبل أي UI** (منطق المال أولًا).
3. شاشة `Discounts\Index` (CRUD + Revoke) + permissions + route + sidebar.
4. تكامل `FeeIssuance` (اكتشاف + تطبيق داخل نفس transaction) + اختبارات الإصدار.
5. تكامل `FeePayment` (سطور أصلي/خصم/صافي + **مسار الصافي 0: تأكيد يدوي بلا رقم وزاري + تخطي إيداع المحفظة عند الصفر — Q3/BR-13**) + `print-tickets` + التقارير (`StudentFinancialStatus`, `DailyPayments`).
6. "تطبيق على الحافظة القائمة" (BR-7) + استيراد CSV (FR-6).
7. Pint + `php artisan test --compact` + سيناريو demo كامل ومراجعة يدوية على المتصفح.
