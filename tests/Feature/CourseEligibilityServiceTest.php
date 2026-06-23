<?php

use App\Enums\Semester;
use App\Enums\SemesterStatus;
use App\Models\CertificateType;
use App\Models\Course;
use App\Models\Department;
use App\Models\FailingGradeSetting;
use App\Models\Grade;
use App\Models\Level;
use App\Models\Registration;
use App\Models\RegistrationCourse;
use App\Models\Section;
use App\Models\Student;
use App\Models\Year;
use App\Services\CourseEligibilityService;
use App\Services\CoursePrerequisiteValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createRegistrationTestFixtures(): array
{
    $department = Department::create(['name' => 'علوم حاسب', 'code' => 'CS']);
    $certificateType = CertificateType::create(['name' => 'ثانوية عامة', 'total_score' => 410]);
    $section = Section::create(['name' => 'شعبة أ', 'department_id' => $department->id, 'cgpa' => 2.0]);
    $level1 = Level::create(['name' => 'الفرقة الأولى']);
    $level2 = Level::create(['name' => 'الفرقة الثانية']);

    $pendingGrade = Grade::create(['name' => 'Pending', 'is_pending_default' => true, 'order' => 0]);
    $failGrade = Grade::create(['name' => 'F', 'is_pending_default' => false, 'order' => 10]);
    $passGrade = Grade::create(['name' => 'B', 'is_pending_default' => false, 'order' => 5]);

    FailingGradeSetting::create(['grade_id' => $failGrade->id]);

    $year1 = Year::create([
        'year' => '2024-2025',
        'first_semester_status' => SemesterStatus::DISABLED,
        'second_semester_status' => SemesterStatus::DISABLED,
        'summer_semester_status' => SemesterStatus::DISABLED,
    ]);

    $year2 = Year::create([
        'year' => '2025-2026',
        'first_semester_status' => SemesterStatus::OPEN_REGISTRATION,
        'second_semester_status' => SemesterStatus::DISABLED,
        'summer_semester_status' => SemesterStatus::DISABLED,
    ]);

    $sport1 = Course::create([
        'code' => 'SP1001',
        'name' => 'رياضة 1',
        'hours' => 2,
        'is_selected' => false,
        'is_active' => true,
        'department_id' => $department->id,
        'level_id' => $level1->id,
        'semester' => 'الأول',
    ]);

    $sport2 = Course::create([
        'code' => 'SP2001',
        'name' => 'رياضة 2',
        'hours' => 2,
        'is_selected' => false,
        'is_active' => true,
        'department_id' => $department->id,
        'level_id' => $level2->id,
        'semester' => 'الأول',
    ]);

    $sport2->prerequisites()->attach($sport1->id);

    $student = Student::create([
        'name' => 'طالب تجريبي',
        'certificate_type_id' => $certificateType->id,
        'national_id' => '29901010101010',
        'username' => 'CS250001',
        'password' => bcrypt('password'),
        'plain_password' => 'password',
        'section_id' => $section->id,
        'level_id' => $level2->id,
        'year_id' => $year2->id,
        'semester' => Semester::FIRST->value,
    ]);

    return compact(
        'department',
        'section',
        'level1',
        'level2',
        'pendingGrade',
        'failGrade',
        'passGrade',
        'year1',
        'year2',
        'sport1',
        'sport2',
        'student'
    );
}

test('prevents circular prerequisite dependencies', function () {
    $department = Department::create(['name' => 'تخصص', 'code' => 'X']);
    $level = Level::create(['name' => 'فرقة 1']);

    $courseA = Course::create([
        'code' => 'A001',
        'name' => 'مادة أ',
        'hours' => 3,
        'department_id' => $department->id,
        'level_id' => $level->id,
        'semester' => 'الأول',
    ]);

    $courseB = Course::create([
        'code' => 'B001',
        'name' => 'مادة ب',
        'hours' => 3,
        'department_id' => $department->id,
        'level_id' => $level->id,
        'semester' => 'الأول',
    ]);

    $courseA->prerequisites()->attach($courseB->id);

    $validator = app(CoursePrerequisiteValidator::class);

    expect($validator->validate($courseB->id, [$courseA->id]))
        ->toBe('لا يمكن إضافة هذا المتطلب لأنه يُنشئ تبعية دائرية.');
});

test('sport 2 is hidden until sport 1 is passed', function () {
    $fixtures = createRegistrationTestFixtures();
    extract($fixtures);

    $service = app(CourseEligibilityService::class);

    $registration = Registration::create([
        'student_id' => $student->id,
        'year_id' => $year1->id,
        'semester' => Semester::FIRST->value,
    ]);

    RegistrationCourse::create([
        'registration_id' => $registration->id,
        'course_id' => $sport1->id,
        'grade_id' => $failGrade->id,
    ]);

    $buckets = $service->getBuckets($student, $year2, Semester::FIRST);

    expect($buckets['due']->pluck('id')->contains($sport2->id))->toBeFalse();
    expect($buckets['retake']->pluck('id')->contains($sport1->id))->toBeTrue();
});

test('sport 2 appears automatically after sport 1 is passed', function () {
    $fixtures = createRegistrationTestFixtures();
    extract($fixtures);

    $service = app(CourseEligibilityService::class);

    $registration = Registration::create([
        'student_id' => $student->id,
        'year_id' => $year1->id,
        'semester' => Semester::SECOND->value,
    ]);

    RegistrationCourse::create([
        'registration_id' => $registration->id,
        'course_id' => $sport1->id,
        'grade_id' => $passGrade->id,
    ]);

    $buckets = $service->getBuckets($student, $year2, Semester::FIRST);

    expect($buckets['due']->pluck('id')->contains($sport2->id))->toBeTrue();
    expect($buckets['retake']->pluck('id')->contains($sport1->id))->toBeFalse();
});

test('grade observer enforces single pending default', function () {
    $first = Grade::create(['name' => 'Pending 1', 'is_pending_default' => true, 'order' => 0]);
    $second = Grade::create(['name' => 'Pending 2', 'is_pending_default' => true, 'order' => 1]);

    $first->refresh();
    $second->refresh();

    expect($first->is_pending_default)->toBeFalse();
    expect($second->is_pending_default)->toBeTrue();
});
