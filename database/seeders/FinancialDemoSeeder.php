<?php

namespace Database\Seeders;

use App\Enums\DiscountMode;
use App\Enums\DiscountScope;
use App\Enums\RegistrationStatus;
use App\Enums\Semester;
use App\Enums\Student\ApplicationCategory;
use App\Enums\Student\StudentStatus;
use App\Enums\Student\StudyStatus;
use App\Models\AcademicAdvisor;
use App\Models\AdditionalFee;
use App\Models\CertificateType;
use App\Models\Course;
use App\Models\DailyPaymentDateTime;
use App\Models\Department;
use App\Models\FeeTemplate;
use App\Models\Grade;
use App\Models\Level;
use App\Models\MilitaryEducationCourse;
use App\Models\Registration;
use App\Models\RegistrationCourse;
use App\Models\RegistrationFee;
use App\Models\Section;
use App\Models\Setting;
use App\Models\Student;
use App\Models\StudentDiscount;
use App\Models\StudentFeeTicket;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\Year;
use App\Services\DiscountService;
use App\Services\RegistrationBillingService;
use App\Services\WalletService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Layers a finance-heavy walkthrough on top of DemoDataSeeder: every wallet-report
 * scenario gets at least one dedicated student — fully paid, partially paid with a
 * discount, never paid, full-discount zero payment, cancelled noise, cross-year and
 * cross-semester tickets, a second department, cash/visa/mixed payments spread over
 * several treasury days, ministerial receipts, and live wallet movements (deposits,
 * registration withdrawals, refunds).
 *
 * Safe to re-run — every record is keyed on a natural identifier.
 *
 * php artisan db:seed --class=FinancialDemoSeeder
 */
class FinancialDemoSeeder extends Seeder
{
    private const PASSWORD = 'Demo@1234';

    private const RECEIPT_START = 5000;

    private const RECEIPT_END = 5099;

    /** @var array<string, Student> */
    private array $students = [];

    /** @var array<string, array{start: Carbon, end: ?Carbon}> */
    private array $days = [];

    private int $receiptCursor;

    public function run(): void
    {
        $this->call(PermissionsSeeder::class);
        $this->call(DefaultAdminSeeder::class);
        $this->call(DemoDataSeeder::class);

        $year = Year::where('year', '2025-2026')->firstOrFail();
        $admin = User::where('is_super_admin', true)->firstOrFail();

        $this->prepareReceipts();
        $this->seedExtraReferenceData($year);
        $this->seedTreasuryDays();
        $this->seedFinanceStudents($year);
        $this->seedPaymentScenarios($year, $admin);
        $this->backfillExistingPaymentData();

        $this->command?->newLine();
        $this->command?->info('تم إنشاء البيانات المالية للتجربة.');
        $this->command?->table(
            ['الكود', 'الطالب', 'السيناريو المستهدف'],
            [
                ['FS250101', 'أحمد كامل السيد', 'مسدد بالكامل (نقدي + فيزا + عسكرية) داخل يوم مالي مغلق'],
                ['FS250102', 'سارة محمود', 'مسدد جزئياً بخصم مطبق — حافظة إضافية غير مسددة'],
                ['FS250103', 'محمد إبراهيم', 'غير مسدد إطلاقاً + استرداد 300 ج.م في المحفظة'],
                ['FS250104', 'ليلى حسن', 'سداد بخصم كامل (صافي صفر) + مصاريف أخرى نقدية'],
                ['FS250105', 'خالد سمير', 'مدفوع أكبر من 7000 + حافظة ملغية تتجاهلها التقارير + خصم نشط'],
                ['FS250106', 'منى فؤاد', 'حافظة مسددة في سنة قديمة وأخرى مستحقة في الحالية'],
                ['FS250107', 'تامر سعيد', 'حافظة مسددة في الترم الثاني ومستحقة في الثاني أيضاً'],
                ['FS250108', 'هند رامي', 'قسم المحاسبة — مسددة نقدي+فيزا وعليها رسوم إضافية'],
            ]
        );
        $this->command?->newLine();
        $this->command?->info('كلمة المرور لكل الطلاب: '.self::PASSWORD);
    }

    private function prepareReceipts(): void
    {
        $setting = Setting::query()->firstOrCreate([]);

        if ($setting->ministerial_receipt_start === null) {
            $setting->update([
                'ministerial_receipt_start' => self::RECEIPT_START,
                'ministerial_receipt_end' => self::RECEIPT_END,
                'ministerial_receipt_current' => self::RECEIPT_START - 1,
            ]);
        }

        $this->receiptCursor = (int) $setting->ministerial_receipt_current;
    }

    private function seedExtraReferenceData(Year $year): void
    {
        $levels = Level::query()->orderBy('id')->get();

        $accounting = Department::firstOrCreate(
            ['code' => 'ACC'],
            ['name' => 'محاسبة', 'course_code' => 'ACC']
        );

        Section::firstOrCreate(
            ['name' => 'شعبة محاسبة 1', 'department_id' => $accounting->id],
            ['cgpa' => 2.0]
        );

        RegistrationFee::updateOrCreate(
            ['department_id' => $accounting->id, 'level_id' => $levels->first()->id],
            [
                'hour_payment' => 200,
                'ministerial_payment' => 600,
                'hour_payment_remaining' => 0,
                'ministerial_payment_remaining' => 0,
                'student_registration_hour' => 18,
                'total_student_payment' => 4200,
                'number_of_students_per_section' => 40,
            ]
        );

        $printing = AdditionalFee::firstOrCreate(
            ['name' => 'رسوم التصوير والطباعة', 'year_id' => $year->id],
            ['gender' => 'both', 'amount' => 150, 'is_one_time' => false, 'semester' => Semester::FIRST->value]
        );
        $printing->items()->firstOrCreate(['name' => 'تصوير المحاضرات'], ['amount' => 100]);
        $printing->items()->firstOrCreate(['name' => 'ملفات وملازم'], ['amount' => 50]);

        AdditionalFee::firstOrCreate(
            ['name' => 'اشتراك اتحاد الطلاب', 'year_id' => $year->id],
            ['gender' => 'both', 'amount' => 200, 'is_one_time' => true, 'semester' => Semester::FIRST->value]
        );
        AdditionalFee::firstOrCreate(
            ['name' => 'رسوم مشاريع التخرج', 'year_id' => $year->id],
            ['gender' => 'both', 'amount' => 1000, 'is_one_time' => true, 'semester' => Semester::FIRST->value]
        );
        AdditionalFee::firstOrCreate(
            ['name' => 'رسوم معمل خاص', 'year_id' => $year->id],
            ['gender' => 'both', 'amount' => 4500, 'is_one_time' => true, 'semester' => Semester::FIRST->value]
        );

        MilitaryEducationCourse::firstOrCreate(
            ['name' => 'التربية العسكرية - الفرق الأولى'],
            ['gender' => 'male', 'capacity' => 60, 'fee_amount' => 300, 'status' => 'active']
        );

        $issuerId = User::query()->value('id');
        FeeTemplate::firstOrCreate(['name' => 'رسوم سحب اختبار'], ['amount' => 150, 'is_active' => true, 'created_by_user_id' => $issuerId]);
        FeeTemplate::firstOrCreate(['name' => 'طباعة بيان حالة'], ['amount' => 100, 'is_active' => true, 'created_by_user_id' => $issuerId]);
        FeeTemplate::firstOrCreate(['name' => 'كتب جامعية'], ['amount' => 200, 'is_active' => true, 'created_by_user_id' => $issuerId]);
        FeeTemplate::firstOrCreate(['name' => 'رسوم تأجيل امتحان'], ['amount' => 700, 'is_active' => true, 'created_by_user_id' => $issuerId]);
    }

    private function seedTreasuryDays(): void
    {
        $windows = [
            'd3' => [now()->subDays(3)->setTime(9, 0), now()->subDays(3)->setTime(14, 0)],
            'd2' => [now()->subDays(2)->setTime(9, 0), now()->subDays(2)->setTime(14, 0)],
            'd1' => [now()->subDays(1)->setTime(9, 0), now()->subDays(1)->setTime(14, 0)],
            'today' => [now()->startOfDay()->addMinute(), null],
        ];

        foreach ($windows as $key => [$start, $end]) {
            $day = DailyPaymentDateTime::whereDate('date', $start->toDateString())->first()
                ?? DailyPaymentDateTime::create(['date' => $start->toDateString(), 'start_date' => $start, 'end_date' => $end]);

            $this->days[$key] = [
                'start' => Carbon::parse($day->start_date),
                'end' => $day->end_date ? Carbon::parse($day->end_date) : null,
            ];
        }
    }

    private function seedFinanceStudents(Year $year): void
    {
        $levels = Level::query()->orderBy('id')->get();
        $certificateTypeId = CertificateType::where('name', 'الثانوية العامة - علمي رياضة')->value('id');

        $advisor = AcademicAdvisor::firstOrCreate(
            ['username' => 'advisor2'],
            [
                'name' => 'د. هالة منصور',
                'password' => Hash::make(self::PASSWORD),
                'max_students' => 100,
                'is_active' => true,
            ]
        );

        $specs = [
            ['FS250101', 'أحمد كامل السيد', 'male', 'علوم الحاسب', 'freshman', 'registered'],
            ['FS250102', 'سارة محمود', 'female', 'علوم الحاسب', 'remaining', 'registered'],
            ['FS250103', 'محمد إبراهيم', 'male', 'علوم الحاسب', 'freshman', 'registered'],
            ['FS250104', 'ليلى حسن', 'female', 'علوم الحاسب', 'freshman', 'registered'],
            ['FS250105', 'خالد سمير', 'male', 'علوم الحاسب', 'remaining', 'registered'],
            ['FS250106', 'منى فؤاد', 'female', 'علوم الحاسب', 'external', 'excused'],
            ['FS250107', 'تامر سعيد', 'male', 'علوم الحاسب', 'freshman', 'registered'],
            ['FS250108', 'هند رامي', 'female', 'محاسبة', 'freshman', 'registered'],
        ];

        foreach ($specs as [$username, $name, $gender, $departmentName, $studyStatus, $status]) {
            $department = Department::where('name', $departmentName)->firstOrFail();
            $section = Section::where('department_id', $department->id)->orderBy('id')->firstOrFail();

            $this->students[$username] = Student::firstOrCreate(
                ['username' => $username],
                [
                    'name' => $name,
                    'certificate_type_id' => $certificateTypeId,
                    'national_id' => $this->nationalId($username),
                    'gender' => $gender,
                    'status' => StudentStatus::from($status),
                    'study_status' => StudyStatus::from($studyStatus),
                    'application_category' => ApplicationCategory::DIRECT,
                    'section_id' => $section->id,
                    'level_id' => $levels->first()->id,
                    'year_id' => $year->id,
                    'semester' => Semester::FIRST->value,
                    'academic_advisor_id' => $advisor->id,
                    'password' => self::PASSWORD,
                    'plain_password' => self::PASSWORD,
                    'military_education_passed' => false,
                ]
            );
        }
    }

    private function seedPaymentScenarios(Year $year, User $admin): void
    {
        $pastYear = Year::firstOrCreate(
            ['year' => '2024-2025'],
            [
                'first_semester_status' => 'disabled',
                'second_semester_status' => 'disabled',
                'summer_semester_status' => 'disabled',
            ]
        );

        // FS250101 — fully paid across three fee types inside the closed day-3 window.
        $this->pay($this->issue('FIN101-REG', 'FS250101', $year, Semester::FIRST, 'registration', 3100, 'مصاريف التسجيل - الفرقة الأولى'), 'cash', day: 'd3', time: '10:30', admin: $admin, ministerial: true);
        $this->pay($this->issue('FIN101-PRINT', 'FS250101', $year, Semester::FIRST, 'additional', 150, 'رسوم التصوير والطباعة'), 'credit', day: 'd3', time: '11:30', admin: $admin, visa: '4321');
        $this->pay($this->issue('FIN101-MIL', 'FS250101', $year, Semester::FIRST, 'military_education', 300, 'التربية العسكرية - الفرق الأولى'), 'cash', day: 'd3', time: '12:30', admin: $admin);

        // FS250102 — study ticket discounted 3100 -> 2500 and paid mixed; graduation fee still pending.
        $this->pay($this->issue('FIN102-REG', 'FS250102', $year, Semester::FIRST, 'registration', 2500, 'مصاريف التسجيل - الفرقة الأولى', original: 3100, discount: 600), 'both', day: 'd2', time: '10:30', admin: $admin, ministerial: true);
        $this->issue('FIN102-PROJECT', 'FS250102', $year, Semester::FIRST, 'additional', 1000, 'رسوم مشاريع التخرج');

        // FS250103 — never pays: three pending tickets across all fee types + refund later.
        $this->issue('FIN103-REG', 'FS250103', $year, Semester::FIRST, 'registration', 3100, 'مصاريف التسجيل - الفرقة الأولى');
        $this->issue('FIN103-UNION', 'FS250103', $year, Semester::FIRST, 'additional', 200, 'اشتراك اتحاد الطلاب');
        $this->issue('FIN103-BOOKS', 'FS250103', $year, Semester::FIRST, 'other', 200, 'كتب جامعية');

        // FS250104 — full-discount zero-amount payment plus a tiny cash fee on day 1.
        $this->pay($this->issue('FIN104-REG', 'FS250104', $year, Semester::FIRST, 'registration', 0, 'مصاريف التسجيل - الفرقة الأولى', original: 3100, discount: 3100), 'cash', day: 'd1', time: '10:00', admin: $admin);
        $this->pay($this->issue('FIN104-EXAM', 'FS250104', $year, Semester::FIRST, 'other', 150, 'رسوم سحب اختبار'), 'cash', day: 'd1', time: '11:00', admin: $admin);

        // FS250105 — big payer for range filters; cancelled noise must be ignored.
        $this->pay($this->issue('FIN105-REG', 'FS250105', $year, Semester::FIRST, 'registration', 3100, 'مصاريف التسجيل - الفرقة الأولى'), 'credit', day: 'd1', time: '12:00', admin: $admin, visa: '8765', ministerial: true);
        $this->pay($this->issue('FIN105-LAB', 'FS250105', $year, Semester::FIRST, 'additional', 4500, 'رسوم معمل خاص'), 'cash', day: 'today', admin: $admin);
        $this->issue('FIN105-MIL', 'FS250105', $year, Semester::FIRST, 'military_education', 300, 'التربية العسكرية - الفرق الأولى');
        $this->issue('FIN105-CANCELLED', 'FS250105', $year, Semester::FIRST, 'other', 9999, 'حافظه ملغيه بالخطأ')->update(['status' => 'cancelled']);

        if (! StudentDiscount::where('decision_number', 'قرار-٧٧٧')->exists()) {
            app(DiscountService::class)->grant([
                'student_id' => $this->students['FS250105']->id,
                'scope' => DiscountScope::Any->value,
                'year_id' => $year->id,
                'semester' => Semester::FIRST->value,
                'mode' => DiscountMode::Fixed->value,
                'value' => '400.00',
                'reason' => 'خصم دفعة مقدمة',
                'decision_number' => 'قرار-٧٧٧',
            ], $admin);
        }

        // FS250106 — cross-year: paid in the old year, still owes in the current one.
        $this->pay($this->issue('FIN106-REG-OLD', 'FS250106', $pastYear, Semester::FIRST, 'registration', 1500, 'مصاريف التسجيل - سنة سابقة'), 'cash', day: 'today', admin: $admin, ministerial: true);
        $this->issue('FIN106-REG', 'FS250106', $year, Semester::FIRST, 'registration', 2000, 'مصاريف التسجيل - الفرقة الأولى');

        // FS250107 — second-semester lifecycle.
        $this->pay($this->issue('FIN107-REG2', 'FS250107', $year, Semester::SECOND, 'registration', 2600, 'مصاريف التسجيل - الترم الثاني'), 'cash', day: 'today', admin: $admin, ministerial: true);
        $this->issue('FIN107-DEFER', 'FS250107', $year, Semester::SECOND, 'other', 700, 'رسوم تأجيل امتحان');

        // FS250108 — second department, mixed payment today, one pending additional fee.
        $this->pay($this->issue('FIN108-REG', 'FS250108', $year, Semester::FIRST, 'registration', 3400, 'مصاريف التسجيل - محاسبة'), 'both', day: 'today', admin: $admin, ministerial: true);
        $this->issue('FIN108-PRINT', 'FS250108', $year, Semester::FIRST, 'additional', 150, 'رسوم التصوير والطباعة');

        Setting::query()->update(['ministerial_receipt_current' => $this->receiptCursor]);

        // Real wallet flows: approved registrations charged from balances + one manual refund.
        $this->chargeRegistration('FS250101', $year, $admin);
        $this->chargeRegistration('FS250102', $year, $admin);

        $refundExists = WalletTransaction::query()
            ->where('student_id', $this->students['FS250103']->id)
            ->where('reason', 'استرداد رسوم كتاب ملغي')
            ->exists();

        if (! $refundExists) {
            app(WalletService::class)->refund(
                student: $this->students['FS250103'],
                amount: 300,
                yearId: $year->id,
                semester: Semester::FIRST,
                reason: 'استرداد رسوم كتاب ملغي',
                performedBy: $admin,
            );
        }
    }

    private function issue(
        string $key,
        string $username,
        Year $year,
        Semester $semester,
        string $feeType,
        float $amount,
        string $feeName,
        ?float $original = null,
        ?float $discount = null,
    ): StudentFeeTicket {
        $student = $this->students[$username];

        $feeId = match ($feeType) {
            'registration' => (int) RegistrationFee::query()
                ->where('department_id', $student->section->department_id)
                ->where('level_id', $student->level_id)
                ->value('id'),
            'additional' => (int) AdditionalFee::where('name', $feeName)->value('id'),
            'military_education' => (int) MilitaryEducationCourse::where('name', $feeName)->value('id'),
            default => 0,
        };

        return StudentFeeTicket::firstOrCreate(
            ['ticket_number' => $key],
            [
                'student_id' => $student->id,
                'fee_type' => $feeType,
                'fee_id' => $feeId,
                'fee_name' => $feeName,
                'amount' => $amount,
                'original_amount' => $original,
                'discount_amount' => $discount ?? 0,
                'status' => 'pending',
                'year_id' => $year->id,
                'semester' => $semester->value,
                'department_id' => $student->section?->department_id,
                'level_id' => $student->level_id,
                'section_id' => $student->section_id,
                'gender' => $student->gender,
            ]
        );
    }

    private function pay(
        StudentFeeTicket $ticket,
        string $method,
        string $day,
        User $admin,
        ?string $time = null,
        bool $ministerial = false,
        ?string $visa = null,
    ): void {
        if ($ticket->status === 'paid') {
            return;
        }

        $window = $this->days[$day];
        $paidAt = $window['end'] !== null
            ? $window['start']->copy()->setTimeFromTimeString($time ?? '10:00')
            : now()->subMinutes(5)->max(Carbon::parse($window['start'])->addSecond());

        if ($ministerial && $ticket->ministerial_receipt_number === null) {
            $this->receiptCursor++;
        }

        $ticket->update([
            'status' => 'paid',
            'payment_method' => $method,
            'visa_last_four' => $visa,
            'paid_at' => $paidAt,
            'ministerial_receipt_number' => $ministerial ? (string) $this->receiptCursor : $ticket->ministerial_receipt_number,
        ]);

        if ((float) $ticket->amount <= 0) {
            return;
        }

        $existingDeposit = WalletTransaction::query()
            ->where('reference_type', $ticket->getMorphClass())
            ->where('reference_id', $ticket->id)
            ->where('type', 'deposit')
            ->exists();

        if ($existingDeposit) {
            return;
        }

        $transaction = app(WalletService::class)->deposit(
            student: $ticket->student,
            amount: (float) $ticket->amount,
            yearId: $ticket->year_id,
            semester: $ticket->semester,
            reason: 'إيداع مبلغ مالي من سداد حافظة',
            reference: $ticket,
            performedBy: $admin,
        );

        $transaction->forceFill(['created_at' => $paidAt])->save();
    }

    private function chargeRegistration(string $username, Year $year, User $admin): void
    {
        $student = $this->students[$username];
        $pendingGrade = Grade::where('is_pending_default', true)->first();
        $course = Course::where('code', 'CS101')->first();

        if ($course === null || $pendingGrade === null) {
            return;
        }

        $registration = Registration::firstOrCreate(
            ['student_id' => $student->id, 'year_id' => $year->id, 'semester' => Semester::FIRST->value],
            ['status' => RegistrationStatus::APPROVED]
        );

        RegistrationCourse::firstOrCreate(
            ['registration_id' => $registration->id, 'course_id' => $course->id],
            ['grade_id' => $pendingGrade->id]
        );

        app(RegistrationBillingService::class)->settle($registration, $admin);
    }

    private function backfillExistingPaymentData(): void
    {
        StudentFeeTicket::query()
            ->where('status', 'paid')
            ->whereNull('payment_method')
            ->update(['payment_method' => 'cash']);

        $morphClass = (new User)->getMorphClass();

        WalletTransaction::query()
            ->whereNull('performed_by_id')
            ->where('reason', 'إيداع مبلغ مالي من سداد حافظة')
            ->update(['performed_by_type' => $morphClass, 'performed_by_id' => User::query()->value('id')]);
    }

    private function nationalId(string $username): string
    {
        return '3'.substr(str_pad((string) (abs(crc32($username)) % 100000000000000), 13, '0', STR_PAD_LEFT), 0, 13);
    }
}
