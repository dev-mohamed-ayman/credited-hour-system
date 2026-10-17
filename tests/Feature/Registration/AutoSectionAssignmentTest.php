<?php

use App\Enums\Semester;
use App\Models\RegistrationFee;
use App\Models\Student;
use App\Services\CourseRegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->world = billingWorld();
    fundWallet($this->world['student'], 5000, $this->world['year']);
});

function registerWorldStudent(array $world, ?Student $student = null): array
{
    auth('web')->login($world['admin']);

    return app(CourseRegistrationService::class)->save(
        $student ?? $world['student'],
        $world['year'],
        Semester::FIRST,
        $world['courses']->pluck('id')->all(),
    );
}

function setStudentsPerSection(array $world, int $perSection): void
{
    RegistrationFee::where('department_id', $world['department']->id)
        ->where('level_id', $world['level']->id)
        ->update(['number_of_students_per_section' => $perSection]);
}

function sectionmate(array $world, int $index, ?int $sectionNumber): Student
{
    return Student::create([
        'name' => 'زميل '.$index,
        'certificate_type_id' => $world['student']->certificate_type_id,
        'national_id' => '2990101010'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
        'username' => 'CS26'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
        'password' => bcrypt('password'),
        'plain_password' => 'password',
        'section_id' => $world['section']->id,
        'level_id' => $world['level']->id,
        'year_id' => $world['year']->id,
        'section_number' => $sectionNumber,
    ]);
}

it('assigns the student a section as soon as courses are registered', function () {
    setStudentsPerSection($this->world, 3);

    expect(registerWorldStudent($this->world)['success'])->toBeTrue()
        ->and($this->world['student']->fresh()->section_number)->toBe(1);
});

it('places the student in the first section with a free seat', function () {
    setStudentsPerSection($this->world, 2);
    sectionmate($this->world, 1, 1);
    sectionmate($this->world, 2, 1);
    sectionmate($this->world, 3, 2);

    registerWorldStudent($this->world);

    expect($this->world['student']->fresh()->section_number)->toBe(2);
});

it('opens a new section when all existing sections are full', function () {
    setStudentsPerSection($this->world, 1);
    sectionmate($this->world, 1, 1);

    registerWorldStudent($this->world);

    expect($this->world['student']->fresh()->section_number)->toBe(2);
});

it('keeps the section of an already distributed student', function () {
    setStudentsPerSection($this->world, 5);
    $this->world['student']->update(['section_number' => 4]);

    registerWorldStudent($this->world);

    expect($this->world['student']->fresh()->section_number)->toBe(4);
});

it('still registers courses when no section size is configured', function () {
    expect(registerWorldStudent($this->world)['success'])->toBeTrue()
        ->and($this->world['student']->fresh()->section_number)->toBeNull();
});

it('does not assign a section when the registration fails', function () {
    setStudentsPerSection($this->world, 3);

    auth('web')->login($this->world['admin']);
    $result = app(CourseRegistrationService::class)->save($this->world['student'], $this->world['year'], Semester::FIRST, []);

    expect($result['success'])->toBeFalse()
        ->and($this->world['student']->fresh()->section_number)->toBeNull();
});
