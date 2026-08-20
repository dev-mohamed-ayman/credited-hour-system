# تحويل الطالب من تخصص إلى تخصص (Student Department Transfer)

**التاريخ:** 2026-08-20
**الحالة:** معتمد

## المشكلة

الطالب المقيّد على تخصص (عربي / English / علوم الحاسب) ممكن يحتاج ينتقل لتخصص تاني.
المشكلة إن التخصصين مش متطابقين ماليًا ولا أكاديميًا:

- `registration_fees` مفتاحها `department_id + level_id` — سعر الساعة والرسوم الوزارية بيختلفوا.
- `student_fee_tickets` بتخزن لقطة `department_id / level_id / section_id` وقت الإصدار.
- `registrations` بتخصم من محفظة الطالب حسب رسوم تخصصه وقت الموافقة (`charged_amount`).

فلو الطالب دفع مصاريف أو سجّل مواد، مجرد تغيير `section_id` بيسيب بيانات مالية وأكاديمية غلط.

## الحل

طلب تحويل بمسار موافقة: موظف بينشئ الطلب، وصاحب صلاحية الموافقة بيراجع تفاصيله ثم يوافق أو يرفض.
الموافقة بتنفّذ **عملية عكس** ذرّية بترجّع الوضع المالي والأكاديمي للترم الحالي وترجّع الفلوس للمحفظة،
ثم بتنقل الطالب للتخصص الجديد.

## القرارات المعتمدة

| القرار | الاختيار |
|---|---|
| نطاق الاسترجاع | العام والترم الحالي بس. التاريخ الأكاديمي القديم مايتلمسش. |
| الحوافظ المدفوعة | تتلغى (`cancelled`) وفلوسها ترجع للمحفظة كـ `refund`. |
| الحوافظ المعلّقة | تتلغى بدون حركة مالية. |
| المواد المسجّلة | سجلات الترم الحالي تتلغى بالكامل (`CANCELLED`) وفلوسها ترجع. |
| المعادلة الأكاديمية | يدوية بعد كده من شاشة «معادلة المحوّلين» الموجودة. |
| هدف التحويل | تخصص + شعبة + فرقة. |
| المصاريف الجديدة | ماتتصدرش أوتوماتيك — المالية بتصدرها من شاشة إصدار الحوافظ. |
| عرض التفاصيل | معاينة حية وهو `pending`، ولقطة ثابتة (`reversal_snapshot`) بعد الموافقة. |
| تسجيل ما حدث | عمود JSON على الطلب نفسه، مش جدول أبناء. |
| إلغاء التسجيلات | حالة `CANCELLED` جديدة، مش حذف. |
| الصلاحيات | موديول جديد `student_transfers` بأربع صلاحيات. |

## المعمارية

### الداتا

جدول جديد واحد `student_transfer_requests`:

| العمود | النوع | ملاحظة |
|---|---|---|
| `student_id` | FK students cascadeOnDelete | |
| `from_department_id` / `from_section_id` / `from_level_id` | FK nullOnDelete | لقطة الوضع القديم وقت الإنشاء |
| `to_department_id` / `to_section_id` / `to_level_id` | FK nullOnDelete | الوجهة |
| `year_id` | FK years | الترم اللي هيتعكس |
| `semester` | string | نفس التخزين المستخدم في `registrations` |
| `status` | string default `pending` | pending / approved / rejected |
| `reason` | text nullable | سبب التحويل |
| `rejection_reason` | text nullable | |
| `refunded_amount` | decimal(10,2) default 0 | إجمالي اللي رجع فعلًا |
| `reversal_snapshot` | json nullable | يتكتب وقت الموافقة فقط |
| `created_by_user_id` / `decided_by_user_id` | FK users nullOnDelete | |
| `decided_at` | timestamp nullable | |

فهرس على `(student_id, status)`.

**تعديلات على الموجود:**

- `App\Enums\RegistrationStatus` — إضافة `CANCELLED = 'cancelled'`. العمود في الداتابيز `string`
  فمافيش migration مطلوب.
- `student_fee_tickets.status` فيه `cancelled` بالفعل في الـ enum — مافيش تعديل.

### الطبقات

```
Livewire\Admin\StudentTransfer\Index   ← قائمة الطلبات + إنشاء
Livewire\Admin\StudentTransfer\Show    ← التفاصيل + موافقة/رفض
              ↓
     StudentTransferService            ← كل المنطق، قابل للاختبار مستقلًا
              ↓
  RegistrationBillingService  +  WalletService   (موجودين بالفعل)
```

`StudentTransferService` واجهته:

```php
assertCanCreate(Student $student, int $toSectionId, int $toLevelId): void
preview(StudentTransferRequest $request): array
approve(StudentTransferRequest $request, User $actor): array
reject(StudentTransferRequest $request, User $actor, string $reason): void
```

`preview()` بيرجّع مصفوفة فيها: سجلات التسجيل للترم ومواد كل سجل و`charged_amount`،
الحوافظ المدفوعة، الحوافظ المعلّقة، إجمالي الاسترداد مقسّم لمصدرين، رصيد المحفظة قبل وبعد،
رسوم التخصص القديم والجديد للمقارنة، وقائمة تحذيرات.

`approve()` جوّه `DB::transaction` واحدة وبالترتيب ده:

1. كل تسجيل في نطاق (الطالب، السنة، الترم) → `RegistrationBillingService::refundAll()` ثم `status = CANCELLED`.
2. كل حافظة مدفوعة في نفس النطاق → `WalletService::refund()` بمبلغها ثم `status = cancelled`.
3. كل حافظة معلّقة في نفس النطاق → `status = cancelled` بدون حركة مالية.
4. الطالب → `section_id` و`level_id` الجدد.
5. الطلب → `approved` + `refunded_amount` + `reversal_snapshot` + `decided_by_user_id` + `decided_at`.

كل حركة محفظة بتتسجل بـ `reference` على السجل الأصلي (تسجيل أو حافظة) و`performed_by` = المستخدم
اللي وافق، فبتظهر بالكامل في شاشة إدارة المحفظة الموجودة.

### الحراسة

`TransferRequestException` (مماثل لـ `InsufficientWalletBalanceException` الموجود) بيترمي لما:

- الطالب عنده طلب `pending` بالفعل.
- الشعبة الجديدة = الشعبة الحالية والفرقة الجديدة = الفرقة الحالية.
- الشعبة الجديدة مش تابعة للتخصص المختار.
- محاولة موافقة أو رفض طلب مش `pending`.

### الصلاحيات

```php
'student_transfers' => [
    'label' => 'تحويل التخصص',
    'actions' => ['view' => 'عرض', 'create' => 'إنشاء', 'approve' => 'موافقة', 'reject' => 'رفض'],
],
```

`PermissionsSeeder` بيقرأ من `config/permissions.php` تلقائيًا.
الراوتس بتتحمي بـ `permission:student_transfers.view`، والأفعال بتتأكد جوّه المكوّن بـ `abort_unless`.

### الواجهة

- `student-transfers` — قائمة بفلاتر (بحث بالطالب، تخصص، حالة) وزرار إنشاء بمودال.
- `student-transfers/{transferRequest}` — التفاصيل: بيانات الطالب، من/إلى، لوحة العكس
  (جدول المواد الملغاة، جدول الحوافظ المستردة، الحوافظ الملغاة، الإجماليات، الرصيد قبل/بعد،
  مقارنة رسوم التخصصين)، وأزرار موافقة/رفض تحت `@can`.
- بعد الموافقة اللوحة بتتعرض من `reversal_snapshot` بدل الحساب الحي.
- رابط في السايدبار تحت قسم شئون الطلبة.

## الاختبارات

`tests/Feature/StudentTransfer/StudentTransferTest.php`:

- المعاينة بتحسب إجمالي الاسترداد صح من التسجيلات والحوافظ المدفوعة.
- الموافقة بترجّع الفلوس للمحفظة وبتخلي التسجيلات `CANCELLED` والحوافظ `cancelled`.
- الموافقة بتنقل `section_id` و`level_id` للطالب.
- الرفض مابيحركش أي فلوس ولا بيغيّر الطالب.
- منع طلبين `pending` لنفس الطالب.
- منع الموافقة من مستخدم من غير صلاحية `student_transfers.approve`.
- الترمات السابقة مابتتلمسش.
