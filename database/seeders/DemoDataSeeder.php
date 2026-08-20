<?php

namespace Database\Seeders;

use App\Enums\RegistrationStatus;
use App\Enums\Semester;
use App\Enums\SemesterStatus;
use App\Enums\Student\ApplicationCategory;
use App\Enums\Student\StudentStatus;
use App\Enums\Student\StudentWarningType;
use App\Enums\Student\StudyStatus;
use App\Models\AcademicAdvisor;
use App\Models\CertificateType;
use App\Models\City;
use App\Models\Country;
use App\Models\Course;
use App\Models\CourseRegistrationSetting;
use App\Models\Department;
use App\Models\FailingGradeSetting;
use App\Models\Grade;
use App\Models\Level;
use App\Models\Nationality;
use App\Models\Registration;
use App\Models\RegistrationCourse;
use App\Models\RegistrationFee;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentFeeTicket;
use App\Models\StudentWarning;
use App\Models\Year;
use App\Services\RegistrationBillingService;
use App\Services\WalletService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Fills the app with a walk-through of the registration and billing flow: one student
 * for each state the flow can be in, so every branch can be clicked through by hand.
 *
 * Safe to re-run — every record is keyed on a natural identifier.
 *
 * php artisan db:seed --class=DemoDataSeeder
 */
class DemoDataSeeder extends Seeder
{
    private const PASSWORD = 'Demo@1234';

    private const HOUR_PAYMENT = 150.0;

    private const MINISTERIAL_PAYMENT = 400.0;

    public function run(): void
    {
        $refs = $this->seedReferenceData();
        $year = $this->seedYear();
        $this->seedFees($refs);
        $courses = $this->seedCourses($refs);
        $advisor = $this->seedAdvisor();

        $this->seedStudents($refs, $year, $courses, $advisor);

        $this->command?->newLine();
        $this->command?->info('تم إنشاء بيانات التجربة.');
        $this->command?->table(
            ['الكود الجامعي', 'الحالة', 'ماذا تختبر'],
            [
                ['CS250001', 'رصيد كافٍ، لا مديونية', 'تسجيل مواد ناجح من بوابة الطالب'],
                ['CS250002', 'عليه حافظتان غير مدفوعتين', 'بوابة المصاريف تمنع التسجيل'],
                ['CS250003', 'تسجيل معلّق ينتظر الموافقة', 'موافقة المرشد/الأدمن ثم الخصم'],
                ['CS250004', 'تسجيل معتمد ومخصوم', 'إضافة/حذف مادة ورؤية خصم الفرق'],
                ['CS250005', 'رصيد لا يكفي', 'رفض التسجيل لعدم كفاية الرصيد'],
                ['CS250006', 'إنذاران أكاديميان وسجل سابق', 'الإنذارات وCGPA في لوحة الطالب'],
            ]
        );
        $this->command?->newLine();
        $this->command?->info('كلمة المرور لكل الطلاب والمرشد: '.self::PASSWORD);
        $this->command?->info('المرشد الأكاديمي: advisor1');
    }

    /**
     * @return array<string, mixed>
     */
    private function seedReferenceData(): array
    {
        $country = Country::firstOrCreate(['name' => 'مصر']);
        City::firstOrCreate(['name' => 'القاهرة', 'country_id' => $country->id]);
        Nationality::firstOrCreate(['name' => 'مصري']);

        $certificateType = CertificateType::firstOrCreate(
            ['name' => 'الثانوية العامة - علمي رياضة'],
            ['total_score' => 410]
        );

        $department = Department::firstOrCreate(
            ['code' => 'CS'],
            ['name' => 'علوم الحاسب', 'course_code' => 'CS']
        );

        $section = Section::firstOrCreate(
            ['name' => 'شعبة نظم المعلومات', 'department_id' => $department->id],
            ['cgpa' => 2.0]
        );

        $levels = $this->resolveLevels();

        $this->seedGrades();

        foreach ($levels as $level) {
            foreach ([Semester::FIRST, Semester::SECOND] as $semester) {
                CourseRegistrationSetting::firstOrCreate(
                    ['level_id' => $level->id, 'term_type' => $semester->value],
                    ['max_optional_courses' => 2]
                );
            }
        }

        return compact('certificateType', 'department', 'section', 'levels');
    }

    /**
     * Reuse the levels the app already has rather than adding near-duplicates that
     * differ only in spelling. Only a database with no levels gets a fresh set.
     *
     * @return \Illuminate\Support\Collection<int, Level>
     */
    private function resolveLevels(): \Illuminate\Support\Collection
    {
        $existing = Level::query()->orderBy('id')->get();

        if ($existing->count() >= 4) {
            return $existing->take(4)->values()->mapWithKeys(fn (Level $level, int $i) => [$i + 1 => $level]);
        }

        return collect(['الفرقة الأولى', 'الفرقة الثانية', 'الفرقة الثالثة', 'الفرقة الرابعة'])
            ->mapWithKeys(fn (string $name, int $i) => [$i + 1 => Level::firstOrCreate(['name' => $name])]);
    }

    private function seedGrades(): void
    {
        // `order` doubles as the grade's GPA points, and the column is an unsigned
        // integer, so the scale can only be whole numbers on 0-4.
        $grades = [
            ['name' => 'Pending', 'order' => 0, 'is_pending_default' => true],
            ['name' => 'A+', 'order' => 4, 'is_pending_default' => false],
            ['name' => 'A', 'order' => 4, 'is_pending_default' => false],
            ['name' => 'B', 'order' => 3, 'is_pending_default' => false],
            ['name' => 'C', 'order' => 2, 'is_pending_default' => false],
            ['name' => 'D', 'order' => 1, 'is_pending_default' => false],
            ['name' => 'F', 'order' => 0, 'is_pending_default' => false],
        ];

        foreach ($grades as $grade) {
            Grade::updateOrCreate(['name' => $grade['name']], $grade);
        }

        $failGrade = Grade::where('name', 'F')->first();
        FailingGradeSetting::firstOrCreate(['grade_id' => $failGrade->id]);
    }

    private function seedYear(): Year
    {
        return Year::firstOrCreate(
            ['year' => '2025-2026'],
            [
                'first_semester_status' => SemesterStatus::OPEN_REGISTRATION,
                'second_semester_status' => SemesterStatus::DISABLED,
                'summer_semester_status' => SemesterStatus::DISABLED,
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $refs
     */
    private function seedFees(array $refs): void
    {
        foreach ($refs['levels'] as $level) {
            RegistrationFee::updateOrCreate(
                ['department_id' => $refs['department']->id, 'level_id' => $level->id],
                [
                    'hour_payment' => self::HOUR_PAYMENT,
                    'ministerial_payment' => self::MINISTERIAL_PAYMENT,
                    'hour_payment_remaining' => 0,
                    'ministerial_payment_remaining' => 0,
                    'student_registration_hour' => 18,
                    'total_student_payment' => (18 * self::HOUR_PAYMENT) + self::MINISTERIAL_PAYMENT,
                    'number_of_students_per_section' => 40,
                ]
            );
        }
    }

    /**
     * @param  array<string, mixed>  $refs
     * @return \Illuminate\Support\Collection<int, Course>
     */
    private function seedCourses(array $refs): \Illuminate\Support\Collection
    {
        $catalogue = [
            // [code, name, hours, level index, arabic semester, optional]
            ['CS101', 'مقدمة في البرمجة', 3, 1, 'الأول', false],
            ['CS102', 'رياضيات متقطعة', 3, 1, 'الأول', false],
            ['CS103', 'أساسيات الحاسب', 2, 1, 'الأول', false],
            ['CS104', 'مهارات الاتصال', 2, 1, 'الأول', true],
            ['CS105', 'لغة إنجليزية', 2, 1, 'الأول', true],
            ['CS111', 'هياكل البيانات', 3, 1, 'الثاني', false],
            ['CS112', 'الجبر الخطي', 3, 1, 'الثاني', false],
            ['CS201', 'قواعد البيانات', 3, 2, 'الأول', false],
            ['CS202', 'الخوارزميات', 3, 2, 'الأول', false],
            ['CS203', 'نظم التشغيل', 3, 2, 'الأول', true],
        ];

        $courses = collect($catalogue)->map(function (array $row) use ($refs) {
            [$code, $name, $hours, $levelIndex, $semester, $optional] = $row;

            return Course::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'hours' => $hours,
                    'is_selected' => $optional,
                    'is_active' => true,
                    'department_id' => $refs['department']->id,
                    'level_id' => $refs['levels'][$levelIndex]->id,
                    'semester' => $semester,
                ]
            );
        })->keyBy('code');

        // Data structures builds on intro programming; algorithms builds on data structures.
        $this->linkPrerequisite($courses['CS111'], $courses['CS101']);
        $this->linkPrerequisite($courses['CS202'], $courses['CS111']);

        return $courses;
    }

    private function linkPrerequisite(Course $course, Course $prerequisite): void
    {
        if (! $course->prerequisites()->where('prerequisite_course_id', $prerequisite->id)->exists()) {
            $course->prerequisites()->attach($prerequisite->id);
        }
    }

    private function seedAdvisor(): AcademicAdvisor
    {
        return AcademicAdvisor::firstOrCreate(
            ['username' => 'advisor1'],
            [
                'name' => 'د. أحمد عبد الرحمن',
                'password' => Hash::make(self::PASSWORD),
                'max_students' => 100,
                'is_active' => true,
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $refs
     * @param  \Illuminate\Support\Collection<int, Course>  $courses
     */
    private function seedStudents(array $refs, Year $year, $courses, AcademicAdvisor $advisor): void
    {
        $wallet = app(WalletService::class);
        $billing = app(RegistrationBillingService::class);

        // 1) Clean slate with money in the wallet — registration should just work.
        $ready = $this->makeStudent('CS250001', 'منة الله شريف', $refs, $year, $advisor, 'female');
        $this->fund($ready, 4000, $year);

        // 2) Owes money — the fee gate must block every registration route.
        $blocked = $this->makeStudent('CS250002', 'يوسف مصطفى', $refs, $year, $advisor, 'male');
        $this->ticket($blocked, $year, 3100, 'مصاريف تسجيل - الفرقة الأولى', 'pending');
        $this->ticket($blocked, $year, 250, 'مصاريف أخرى - كتب دراسية', 'pending');

        // 3) Registered themself and is waiting on staff approval — nothing charged yet.
        $pending = $this->makeStudent('CS250003', 'سلمى عادل', $refs, $year, $advisor, 'female');
        $this->fund($pending, 4000, $year);
        $this->registration($pending, $year, [$courses['CS101'], $courses['CS102']], RegistrationStatus::PENDING);

        // 4) Approved and already charged — use it to watch add/remove settle the difference.
        $approved = $this->makeStudent('CS250004', 'كريم هشام', $refs, $year, $advisor, 'male');
        $this->fund($approved, 4000, $year);
        $approvedRegistration = $this->registration(
            $approved, $year, [$courses['CS101'], $courses['CS103']], RegistrationStatus::APPROVED
        );
        $billing->settle($approvedRegistration->refresh());

        // 5) Nothing owed, but the wallet cannot cover a term — approval must be refused.
        $broke = $this->makeStudent('CS250005', 'نور الدين طارق', $refs, $year, $advisor, 'male');
        $this->fund($broke, 200, $year);
        $this->registration($broke, $year, [$courses['CS101'], $courses['CS102']], RegistrationStatus::PENDING);

        // 6) Carries history and warnings, so the dashboard has a CGPA to draw.
        $warned = $this->makeStudent('CS250006', 'هبة سامي', $refs, $year, $advisor, 'female');
        $this->fund($warned, 4000, $year);
        $this->seedHistory($warned, $year, $courses);

        foreach ([['danger', 'انخفاض المعدل التراكمي عن 2.0'], ['warning', 'تعثر في مادة أساسية']] as [$type, $reason]) {
            StudentWarning::firstOrCreate(
                ['student_id' => $warned->id, 'reason' => $reason],
                ['type' => $type === 'danger' ? StudentWarningType::DANGER : StudentWarningType::WARNING, 'is_active' => true]
            );
        }

        unset($wallet);
    }

    /**
     * @param  array<string, mixed>  $refs
     */
    private function makeStudent(
        string $username,
        string $name,
        array $refs,
        Year $year,
        AcademicAdvisor $advisor,
        string $gender
    ): Student {
        return Student::firstOrCreate(
            ['username' => $username],
            [
                'name' => $name,
                'certificate_type_id' => $refs['certificateType']->id,
                'national_id' => '3'.substr($username, -7).str_pad((string) crc32($username) % 1000000, 6, '0', STR_PAD_LEFT),
                'gender' => $gender,
                'status' => StudentStatus::REGISTERED,
                'study_status' => StudyStatus::FRESHMAN,
                'application_category' => ApplicationCategory::DIRECT,
                'section_id' => $refs['section']->id,
                'level_id' => $refs['levels'][1]->id,
                'year_id' => $year->id,
                'semester' => Semester::FIRST->value,
                'academic_advisor_id' => $advisor->id,
                'password' => self::PASSWORD,
                'plain_password' => self::PASSWORD,
                'military_education_passed' => false,
            ]
        );
    }

    private function fund(Student $student, float $amount, Year $year): void
    {
        $wallet = app(WalletService::class);

        if ($wallet->getBalance($student) > 0) {
            return;
        }

        $ticket = $this->ticket($student, $year, $amount, 'مصاريف تسجيل - الفرقة الأولى', 'paid');

        $wallet->deposit(
            student: $student,
            amount: $amount,
            yearId: $year->id,
            semester: Semester::FIRST,
            reason: 'إيداع مبلغ مالي من سداد حافظة',
            reference: $ticket,
        );
    }

    private function ticket(Student $student, Year $year, float $amount, string $name, string $status): StudentFeeTicket
    {
        return StudentFeeTicket::firstOrCreate(
            ['student_id' => $student->id, 'fee_name' => $name, 'amount' => $amount],
            [
                'ticket_number' => date('ymdHis').$student->username.random_int(10, 99),
                'fee_type' => 'registration',
                'fee_id' => 0,
                'status' => $status,
                'paid_at' => $status === 'paid' ? now() : null,
                'year_id' => $year->id,
                'semester' => Semester::FIRST->value,
                'department_id' => $student->section?->department_id,
                'level_id' => $student->level_id,
                'section_id' => $student->section_id,
            ]
        );
    }

    /**
     * @param  array<int, Course>  $courses
     */
    private function registration(Student $student, Year $year, array $courses, RegistrationStatus $status): Registration
    {
        $pendingGrade = Grade::where('is_pending_default', true)->first();

        $registration = Registration::firstOrCreate(
            [
                'student_id' => $student->id,
                'year_id' => $year->id,
                'semester' => Semester::FIRST->value,
            ],
            ['status' => $status]
        );

        foreach ($courses as $course) {
            RegistrationCourse::firstOrCreate(
                ['registration_id' => $registration->id, 'course_id' => $course->id],
                ['grade_id' => $pendingGrade->id]
            );
        }

        return $registration;
    }

    /**
     * A finished earlier term, so cumulative GPA and the transcript have something to show.
     *
     * @param  \Illuminate\Support\Collection<int, Course>  $courses
     */
    private function seedHistory(Student $student, Year $currentYear, $courses): void
    {
        $pastYear = Year::firstOrCreate(
            ['year' => '2024-2025'],
            [
                'first_semester_status' => SemesterStatus::DISABLED,
                'second_semester_status' => SemesterStatus::DISABLED,
                'summer_semester_status' => SemesterStatus::DISABLED,
            ]
        );

        $registration = Registration::firstOrCreate(
            [
                'student_id' => $student->id,
                'year_id' => $pastYear->id,
                'semester' => Semester::FIRST->value,
            ],
            ['status' => RegistrationStatus::APPROVED]
        );

        $results = [['CS101', 'C'], ['CS102', 'D'], ['CS103', 'F']];

        foreach ($results as [$code, $gradeName]) {
            RegistrationCourse::firstOrCreate(
                ['registration_id' => $registration->id, 'course_id' => $courses[$code]->id],
                ['grade_id' => Grade::where('name', $gradeName)->value('id')]
            );
        }

        unset($currentYear);
    }
}
