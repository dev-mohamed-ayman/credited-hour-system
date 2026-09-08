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

/**
 * Lecture-scheduling world: department + level + first-semester course,
 * N empty sections linked via course_section, an active Year, a 300-seat
 * auditorium, and a fully-permissioned admin.
 *
 * @return array{department: \App\Models\Department, certificateType: \App\Models\CertificateType, level: \App\Models\Level, year: \App\Models\Year, course: \App\Models\Course, sections: \Illuminate\Support\Collection, venue: \App\Models\Venue, admin: \App\Models\User}
 */
function schedulingWorld(int $sectionCount = 12): array
{
    foreach (['venues.view', 'venues.create', 'venues.edit', 'venues.delete', 'lecture_schedules.view', 'lecture_schedules.create', 'lecture_schedules.edit', 'lecture_schedules.delete'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    $department = Department::create(['name' => 'محاسبة', 'code' => 'ACC-'.uniqid()]);
    $certificateType = CertificateType::firstOrCreate(['name' => 'ثانوية عامة'], ['total_score' => 410]);
    $level = Level::create(['name' => 'الفرقة الأولى']);

    $year = Year::create([
        'year' => '2025-2026',
        'first_semester_status' => SemesterStatus::OPEN_REGISTRATION,
        'second_semester_status' => SemesterStatus::DISABLED,
        'summer_semester_status' => SemesterStatus::DISABLED,
    ]);

    $course = Course::create([
        'code' => 'ACC-'.uniqid(),
        'name' => 'محاسبه',
        'hours' => 3,
        'is_selected' => false,
        'is_active' => true,
        'department_id' => $department->id,
        'level_id' => $level->id,
        'semester' => 'الأول',
    ]);

    $sections = collect(range(1, $sectionCount))->map(function (int $i) use ($department, $course) {
        $section = Section::create(['name' => (string) $i, 'department_id' => $department->id]);
        $course->sections()->attach($section);

        return $section;
    });

    $venue = \App\Models\Venue::factory()->create(['name' => 'مدرج أ', 'capacity' => 300]);

    $admin = User::factory()->create();
    $admin->givePermissionTo([
        'venues.view', 'venues.create', 'venues.edit', 'venues.delete',
        'lecture_schedules.view', 'lecture_schedules.create', 'lecture_schedules.edit', 'lecture_schedules.delete',
    ]);

    return compact('department', 'certificateType', 'level', 'year', 'course', 'sections', 'venue', 'admin');
}

function seedSectionStudents(\App\Models\Section $section, int $count, array $world): void
{
    for ($i = 0; $i < $count; $i++) {
        Student::create([
            'name' => 'طالب تجريبي',
            'certificate_type_id' => $world['certificateType']->id,
            'national_id' => fake()->unique()->numerify('##############'),
            'username' => fake()->unique()->numerify('2#######'),
            'password' => bcrypt('password'),
            'plain_password' => 'password',
            'section_id' => $section->id,
            'level_id' => $world['level']->id,
            'year_id' => $world['year']->id,
            'semester' => Semester::FIRST->value,
        ]);
    }
}

/**
 * Exam-scheduling world: a year with an open first semester, two courses in
 * one department/level, five students approved in BOTH courses (shared
 * audience for conflict tests), one pending student, and a venue.
 */
function examWorld(int $studentCount = 5): array
{
    foreach (['exam_schedules.view', 'exam_schedules.create', 'exam_schedules.edit', 'exam_schedules.delete', 'exam_schedules.publish'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    $department = Department::create(['name' => 'محاسبة', 'code' => 'EXM-'.uniqid()]);
    $level = Level::create(['name' => 'الفرقة الأولى']);
    $section = Section::create(['name' => '1', 'department_id' => $department->id]);

    $year = Year::create([
        'year' => '2025-2026',
        'first_semester_status' => SemesterStatus::OPEN_REGISTRATION,
        'second_semester_status' => SemesterStatus::DISABLED,
        'summer_semester_status' => SemesterStatus::DISABLED,
    ]);

    $courses = collect(['E1' => 'إحصاء', 'E2' => 'جبر'])->map(fn ($name, $code) => Course::create([
        'code' => $code.'-'.uniqid(),
        'name' => $name,
        'hours' => 3,
        'is_selected' => false,
        'is_active' => true,
        'department_id' => $department->id,
        'level_id' => $level->id,
        'semester' => 'الأول',
    ]));

    $students = collect(range(1, $studentCount))->map(fn ($i) => \App\Models\Student::create([
        'name' => 'طالب '.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
        'certificate_type_id' => CertificateType::firstOrCreate(['name' => 'ثانوية عامة'], ['total_score' => 410])->id,
        'national_id' => '29901010'.str_pad((string) $i, 6, '0', STR_PAD_LEFT),
        'username' => 'EXM'.str_pad((string) $i, 6, '0', STR_PAD_LEFT),
        'password' => bcrypt('password'),
        'plain_password' => 'password',
        'section_id' => $section->id,
        'level_id' => $level->id,
        'year_id' => $year->id,
        'semester' => Semester::FIRST->value,
    ]));

    $grade = Grade::firstOrCreate(['name' => 'Pending'], ['is_pending_default' => true, 'order' => 0]);

    foreach ($students as $student) {
        $registration = \App\Models\Registration::create([
            'student_id' => $student->id,
            'year_id' => $year->id,
            'semester' => Semester::FIRST,
            'status' => \App\Enums\RegistrationStatus::APPROVED,
        ]);

        foreach ($courses as $course) {
            $registration->courses()->create(['course_id' => $course->id, 'grade_id' => $grade->id]);
        }
    }

    $pendingStudent = \App\Models\Student::create([
        'name' => 'طالب قيد الانتظار',
        'certificate_type_id' => CertificateType::firstOrCreate(['name' => 'ثانوية عامة'], ['total_score' => 410])->id,
        'national_id' => '29901011999999',
        'username' => 'EXMPENDING',
        'password' => bcrypt('password'),
        'plain_password' => 'password',
        'section_id' => $section->id,
        'level_id' => $level->id,
        'year_id' => $year->id,
        'semester' => Semester::FIRST->value,
    ]);

    $pendingRegistration = \App\Models\Registration::create([
        'student_id' => $pendingStudent->id,
        'year_id' => $year->id,
        'semester' => Semester::FIRST,
        'status' => \App\Enums\RegistrationStatus::PENDING,
    ]);

    foreach ($courses as $course) {
        $pendingRegistration->courses()->create(['course_id' => $course->id, 'grade_id' => $grade->id]);
    }

    $venue = \App\Models\Venue::factory()->create(['name' => 'مدرج أ', 'capacity' => 300]);

    $admin = User::factory()->create();
    $admin->givePermissionTo([
        'exam_schedules.view', 'exam_schedules.create', 'exam_schedules.edit', 'exam_schedules.delete', 'exam_schedules.publish',
    ]);

    return compact('department', 'level', 'section', 'year', 'courses', 'students', 'pendingStudent', 'venue', 'admin');
}

function examService(): \App\Services\ExamScheduleService
{
    return app(\App\Services\ExamScheduleService::class);
}

function examAttributes(array $world, array $overrides = []): array
{
    return array_merge([
        'course_id' => $world['courses']['E1']->id,
        'year_id' => $world['year']->id,
        'semester' => Semester::FIRST->value,
        'type' => \App\Enums\ExamType::REGULAR->value,
        'exam_date' => '2026-01-15',
        'start_time' => '09:00',
        'end_time' => '11:00',
    ], $overrides);
}
