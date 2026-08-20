<?php

use App\Enums\Semester;
use App\Enums\SemesterStatus;
use App\Models\AcademicAdvisor;
use App\Models\CertificateType;
use App\Models\Course;
use App\Models\Department;
use App\Models\FailingGradeSetting;
use App\Models\Grade;
use App\Models\Level;
use App\Models\RegistrationFee;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentFeeTicket;
use App\Models\User;
use App\Models\Year;
use App\Services\WalletService;
use Spatie\Permission\Models\Permission;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
 // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Builds a student sitting in a department/level that charges 100 per hour
 * plus a flat 500 ministerial fee, with two 3-hour courses available.
 */
function billingWorld(): array
{
    foreach (['course_registrations.view', 'course_registrations.create', 'course_registrations.delete'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    $department = Department::create(['name' => 'علوم حاسب', 'code' => 'CS']);
    $certificateType = CertificateType::create(['name' => 'ثانوية عامة', 'total_score' => 410]);
    $section = Section::create(['name' => 'شعبة أ', 'department_id' => $department->id, 'cgpa' => 2.0]);
    $level = Level::create(['name' => 'الفرقة الأولى']);

    Grade::create(['name' => 'Pending', 'is_pending_default' => true, 'order' => 0]);
    $failGrade = Grade::create(['name' => 'F', 'is_pending_default' => false, 'order' => 10]);
    FailingGradeSetting::create(['grade_id' => $failGrade->id]);

    $year = Year::create([
        'year' => '2025-2026',
        'first_semester_status' => SemesterStatus::OPEN_REGISTRATION,
        'second_semester_status' => SemesterStatus::DISABLED,
        'summer_semester_status' => SemesterStatus::DISABLED,
    ]);

    RegistrationFee::create([
        'department_id' => $department->id,
        'level_id' => $level->id,
        'hour_payment' => 100,
        'ministerial_payment' => 500,
        'total_student_payment' => 2000,
    ]);

    $courses = collect(['CS101', 'CS102'])->map(fn ($code, $i) => Course::create([
        'code' => $code,
        'name' => 'مادة '.$code,
        'hours' => 3,
        'is_selected' => false,
        'is_active' => true,
        'department_id' => $department->id,
        'level_id' => $level->id,
        'semester' => 'الأول',
    ]));

    $student = Student::create([
        'name' => 'طالب تجريبي',
        'certificate_type_id' => $certificateType->id,
        'national_id' => '29901010101011',
        'username' => 'CS250001',
        'password' => bcrypt('password'),
        'plain_password' => 'password',
        'section_id' => $section->id,
        'level_id' => $level->id,
        'year_id' => $year->id,
        'semester' => Semester::FIRST->value,
    ]);

    $admin = User::factory()->create();
    $admin->givePermissionTo(['course_registrations.view', 'course_registrations.create', 'course_registrations.delete']);

    $advisor = AcademicAdvisor::create([
        'name' => 'مرشد تجريبي',
        'username' => 'advisor1',
        'password' => bcrypt('password'),
        'max_students' => 50,
    ]);

    return compact('student', 'admin', 'advisor', 'year', 'courses', 'department', 'level', 'section');
}

function fundWallet(Student $student, float $amount, Year $year): void
{
    app(WalletService::class)->deposit(
        student: $student,
        amount: $amount,
        yearId: $year->id,
        semester: Semester::FIRST,
        reason: 'رصيد اختبار',
    );
}

function issueTicket(Student $student, Year $year, float $amount = 2000, string $status = 'pending'): StudentFeeTicket
{
    return StudentFeeTicket::create([
        'ticket_number' => 'T'.uniqid(),
        'student_id' => $student->id,
        'fee_type' => 'registration',
        'fee_id' => 1,
        'fee_name' => 'مصاريف تسجيل',
        'amount' => $amount,
        'status' => $status,
        'year_id' => $year->id,
        'semester' => Semester::FIRST->value,
    ]);
}

/**
 * billingWorld() plus a second department priced differently, to transfer into.
 */
function transferWorld(): array
{
    $world = billingWorld();

    $permissions = ['student_transfers.view', 'student_transfers.create', 'student_transfers.approve', 'student_transfers.reject'];

    foreach ($permissions as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    $targetDepartment = Department::create(['name' => 'English', 'code' => 'E']);
    $targetSection = Section::create(['name' => 'شعبة إنجليزي', 'department_id' => $targetDepartment->id, 'cgpa' => 2.0]);

    RegistrationFee::create([
        'department_id' => $targetDepartment->id,
        'level_id' => $world['level']->id,
        'hour_payment' => 250,
        'ministerial_payment' => 700,
        'total_student_payment' => 3000,
    ]);

    $world['admin']->givePermissionTo($permissions);

    return $world + compact('targetDepartment', 'targetSection', 'permissions');
}

/**
 * An approved registration for the student's current term, already charged to the wallet.
 */
function chargedRegistration(array $world, int $courseCount = 2): \App\Models\Registration
{
    $registration = \App\Models\Registration::create([
        'student_id' => $world['student']->id,
        'year_id' => $world['year']->id,
        'semester' => Semester::FIRST,
        'status' => \App\Enums\RegistrationStatus::APPROVED,
    ]);

    $pendingGrade = \App\Models\Grade::where('is_pending_default', true)->firstOrFail();

    foreach ($world['courses']->take($courseCount) as $course) {
        \App\Models\RegistrationCourse::create([
            'registration_id' => $registration->id,
            'course_id' => $course->id,
            'grade_id' => $pendingGrade->id,
        ]);
    }

    app(\App\Services\RegistrationBillingService::class)->settle($registration, $world['admin']);

    return $registration->refresh();
}

function makeRequest(array $world, ?string $reason = 'رغبة الطالب'): \App\Models\StudentTransferRequest
{
    return app(\App\Services\StudentTransferService::class)->create(
        student: $world['student'],
        toSectionId: $world['targetSection']->id,
        toLevelId: $world['level']->id,
        reason: $reason,
        actor: $world['admin'],
    );
}
