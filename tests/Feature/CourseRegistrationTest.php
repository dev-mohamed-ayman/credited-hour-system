<?php

use App\Enums\Semester;
use App\Enums\SemesterStatus;
use App\Livewire\Admin\CourseRegistration\Index as CourseRegistrationIndex;
use App\Models\CertificateType;
use App\Models\Course;
use App\Models\CourseRegistrationSetting;
use App\Models\Department;
use App\Models\FailingGradeSetting;
use App\Models\Grade;
use App\Models\Level;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'course_registrations.view', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'course_registrations.create', 'guard_name' => 'web']);
});

test('course registration page shows summer message when current term is summer', function () {
    Year::create([
        'year' => '2025-2026',
        'first_semester_status' => SemesterStatus::DISABLED,
        'second_semester_status' => SemesterStatus::DISABLED,
        'summer_semester_status' => SemesterStatus::OPEN_REGISTRATION,
    ]);

    $user = User::factory()->create();
    $user->givePermissionTo(['course_registrations.view', 'course_registrations.create']);

    Livewire::actingAs($user)
        ->test(CourseRegistrationIndex::class)
        ->assertSet('registrationAvailable', false);
});

test('course registration rejects optional courses beyond limit', function () {
    $department = Department::create(['name' => 'علوم حاسب', 'code' => 'CS']);
    $certificateType = CertificateType::create(['name' => 'ثانوية عامة', 'total_score' => 410]);
    $section = Section::create(['name' => 'شعبة أ', 'department_id' => $department->id, 'cgpa' => 2.0]);
    $level = Level::create(['name' => 'الفرقة الأولى']);

    $pendingGrade = Grade::create(['name' => 'Pending', 'is_pending_default' => true, 'order' => 0]);
    $failGrade = Grade::create(['name' => 'F', 'is_pending_default' => false, 'order' => 10]);
    FailingGradeSetting::create(['grade_id' => $failGrade->id]);

    $year = Year::create([
        'year' => '2025-2026',
        'first_semester_status' => SemesterStatus::OPEN_REGISTRATION,
        'second_semester_status' => SemesterStatus::DISABLED,
        'summer_semester_status' => SemesterStatus::DISABLED,
    ]);

    CourseRegistrationSetting::create([
        'level_id' => $level->id,
        'term_type' => Semester::FIRST->value,
        'max_optional_courses' => 1,
    ]);

    $optional1 = Course::create([
        'code' => 'OPT01',
        'name' => 'اختياري 1',
        'hours' => 2,
        'is_selected' => true,
        'is_active' => true,
        'department_id' => $department->id,
        'level_id' => $level->id,
        'semester' => 'الأول',
    ]);

    $optional2 = Course::create([
        'code' => 'OPT02',
        'name' => 'اختياري 2',
        'hours' => 2,
        'is_selected' => true,
        'is_active' => true,
        'department_id' => $department->id,
        'level_id' => $level->id,
        'semester' => 'الأول',
    ]);

    $student = Student::create([
        'name' => 'طالب تجريبي',
        'certificate_type_id' => $certificateType->id,
        'national_id' => '29901010101011',
        'username' => 'CS250002',
        'password' => bcrypt('password'),
        'plain_password' => 'password',
        'section_id' => $section->id,
        'level_id' => $level->id,
        'year_id' => $year->id,
        'semester' => Semester::FIRST->value,
    ]);

    $user = User::factory()->create();
    $user->givePermissionTo(['course_registrations.view', 'course_registrations.create']);

    Livewire::actingAs($user)
        ->test(CourseRegistrationIndex::class)
        ->set('searchCode', $student->username)
        ->call('search')
        ->set('selectedDue', [$optional1->id, $optional2->id])
        ->call('save')
        ->assertDispatched('toast');

    expect(\App\Models\RegistrationCourse::count())->toBe(1);
});
