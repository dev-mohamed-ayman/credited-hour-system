<?php

use App\Enums\RegistrationStatus;
use App\Enums\Semester;
use App\Enums\Student\StudentStatus;
use App\Livewire\Admin\CourseRegistration\BulkRegister;
use App\Models\Course;
use App\Models\Grade;
use App\Models\Registration;
use App\Models\RegistrationCourse;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->world = billingWorld();
    fundWallet($this->world['student'], 5000, $this->world['year']);
});

function runBulkRegistration(array $world): \Livewire\Features\SupportTesting\Testable
{
    return Livewire::actingAs($world['admin'])
        ->test(BulkRegister::class)
        ->set('yearId', $world['year']->id)
        ->set('semester', Semester::FIRST->value)
        ->call('register')
        ->assertHasNoErrors();
}

it('registers a clean student in all core courses of their level and term', function () {
    $component = runBulkRegistration($this->world);

    $registration = Registration::where('student_id', $this->world['student']->id)->firstOrFail();

    expect($registration->status)->toBe(RegistrationStatus::APPROVED)
        ->and($registration->courses()->pluck('course_id')->sort()->values()->all())
        ->toBe($this->world['courses']->pluck('id')->sort()->values()->all())
        ->and((float) $registration->charged_amount)->toBeGreaterThan(0.0);

    expect($component->get('result')['registered'])->toHaveCount(1);
});

it('skips optional courses and courses of another term', function () {
    $optional = Course::create([
        'code' => 'CS150', 'name' => 'اختياري', 'hours' => 2, 'is_selected' => true, 'is_active' => true,
        'department_id' => $this->world['department']->id, 'level_id' => $this->world['level']->id, 'semester' => 'الأول',
    ]);
    $secondTerm = Course::create([
        'code' => 'CS160', 'name' => 'ترم تاني', 'hours' => 3, 'is_selected' => false, 'is_active' => true,
        'department_id' => $this->world['department']->id, 'level_id' => $this->world['level']->id, 'semester' => 'الثاني',
    ]);

    runBulkRegistration($this->world);

    $registered = RegistrationCourse::pluck('course_id');

    expect($registered)->toHaveCount(2)
        ->not->toContain($optional->id)
        ->not->toContain($secondTerm->id);
});

it('skips a student who has a failed course to retake', function () {
    $oldYear = Year::create(['year' => '2024-2025']);
    $previous = Registration::create([
        'student_id' => $this->world['student']->id,
        'year_id' => $oldYear->id,
        'semester' => Semester::FIRST->value,
        'status' => RegistrationStatus::APPROVED,
    ]);
    RegistrationCourse::create([
        'registration_id' => $previous->id,
        'course_id' => $this->world['courses'][0]->id,
        'grade_id' => Grade::where('name', 'F')->value('id'),
    ]);

    $component = runBulkRegistration($this->world);

    expect(Registration::where('year_id', $this->world['year']->id)->count())->toBe(0)
        ->and($component->get('result')['skipped'][0]['reason'])->toContain('رسوب');
});

it('ignores students who are not actively enrolled', function (StudentStatus $status) {
    $this->world['student']->update(['status' => $status]);

    $component = runBulkRegistration($this->world);

    expect(Registration::count())->toBe(0)
        ->and($component->get('result')['registered'])->toBeEmpty()
        ->and($component->get('result')['skipped'])->toBeEmpty();
})->with([StudentStatus::DISMISSED, StudentStatus::GRADUATED, StudentStatus::SUSPENDED]);

it('skips a student already registered in that term', function () {
    runBulkRegistration($this->world);
    $component = runBulkRegistration($this->world);

    expect(RegistrationCourse::count())->toBe(2)
        ->and($component->get('result')['skipped'][0]['reason'])->toContain('مسجل بالفعل');
});

it('skips a student with unpaid fees and leaves no empty registration behind', function () {
    issueTicket($this->world['student'], $this->world['year']);

    $component = runBulkRegistration($this->world);

    expect(Registration::count())->toBe(0)
        ->and($component->get('result')['skipped'])->toHaveCount(1);
});

it('skips a student whose wallet cannot cover the charge without leaving an empty registration', function () {
    $poorStudent = $this->world['student']->replicate(['username', 'national_id']);
    $poorStudent->forceFill(['username' => 'CS250002', 'national_id' => '29901010101012'])->save();

    $component = runBulkRegistration($this->world);

    expect($component->get('result')['registered'])->toHaveCount(1)
        ->and($component->get('result')['skipped'])->toHaveCount(1)
        ->and(Registration::where('student_id', $poorStudent->id)->exists())->toBeFalse();
});

it('forbids users without the create permission', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(BulkRegister::class)
        ->call('register')
        ->assertForbidden();
});
