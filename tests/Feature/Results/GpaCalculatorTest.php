<?php

use App\Enums\RegistrationStatus;
use App\Enums\Semester;
use App\Models\Course;
use App\Models\Grade;
use App\Models\Registration;
use App\Models\RegistrationCourse;
use App\Models\TransferEquivalency;
use App\Models\Year;
use App\Services\GpaCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->world = billingWorld();
    seedResultScale();
    $this->calculator = app(GpaCalculator::class);
});

function gpaCourse(array $world, string $code, int $hours): Course
{
    return Course::create([
        'code' => $code, 'name' => 'مادة '.$code, 'hours' => $hours, 'is_selected' => false, 'is_active' => true,
        'department_id' => $world['department']->id, 'level_id' => $world['level']->id, 'semester' => 'الأول',
    ]);
}

/**
 * @param  array<string, string>  $gradesByCourseCode
 */
function gpaTerm(array $world, Year $year, Semester $semester, array $gradesByCourseCode, RegistrationStatus $status = RegistrationStatus::APPROVED): Registration
{
    $registration = Registration::create([
        'student_id' => $world['student']->id, 'year_id' => $year->id, 'semester' => $semester->value, 'status' => $status,
    ]);

    foreach ($gradesByCourseCode as $code => $grade) {
        RegistrationCourse::create([
            'registration_id' => $registration->id,
            'course_id' => Course::where('code', $code)->value('id'),
            'grade_id' => Grade::where('name', $grade)->value('id'),
        ]);
    }

    return $registration;
}

it('weights points by hours and counts F with zero points', function () {
    gpaCourse($this->world, 'M1', 3);
    gpaCourse($this->world, 'M2', 2);
    gpaCourse($this->world, 'M3', 3);

    gpaTerm($this->world, $this->world['year'], Semester::FIRST, ['M1' => 'A', 'M2' => 'B+', 'M3' => 'F']);

    $result = $this->calculator->cumulative($this->world['student']);

    expect($result['total_hours'])->toBe(8)
        ->and($result['earned_hours'])->toBe(5)
        ->and($result['gpa'])->toBe(round((3 * 3.7 + 2 * 3.4) / 8, 2));
});

it('ignores Pending, IC and W and rejected registrations', function () {
    gpaCourse($this->world, 'M1', 3);
    gpaCourse($this->world, 'M2', 3);
    gpaCourse($this->world, 'M3', 3);
    gpaCourse($this->world, 'M4', 3);
    gpaCourse($this->world, 'M5', 3);

    gpaTerm($this->world, $this->world['year'], Semester::FIRST, ['M1' => 'B', 'M2' => 'Pending', 'M3' => 'IC', 'M4' => 'W']);
    gpaTerm($this->world, $this->world['year'], Semester::SECOND, ['M5' => 'F'], RegistrationStatus::REJECTED);

    expect($this->calculator->cumulative($this->world['student']))
        ->toMatchArray(['gpa' => 3.0, 'total_hours' => 3, 'earned_hours' => 3]);
});

it('counts a repeated course once by its latest attempt, after transfer equivalencies', function () {
    gpaCourse($this->world, 'M1', 3);
    gpaCourse($this->world, 'M2', 3);
    $nextYear = Year::create(['year' => '2026-2027']);

    TransferEquivalency::create([
        'student_id' => $this->world['student']->id,
        'course_id' => Course::where('code', 'M2')->value('id'),
        'grade_id' => Grade::where('name', 'C')->value('id'),
    ]);

    gpaTerm($this->world, $nextYear, Semester::FIRST, ['M1' => 'B']);
    gpaTerm($this->world, $this->world['year'], Semester::FIRST, ['M1' => 'F']);
    gpaTerm($this->world, $this->world['year'], Semester::SECOND, ['M2' => 'A+']);
    gpaTerm($this->world, $nextYear, Semester::SECOND, ['M1' => 'W']);

    $result = $this->calculator->cumulative($this->world['student']);

    expect($result['total_hours'])->toBe(6)
        ->and($result['earned_hours'])->toBe(6)
        ->and($result['gpa'])->toBe(round((3 * 3.0 + 3 * 4.0) / 6, 2));
});

it('computes a single term without de-duplication', function () {
    gpaCourse($this->world, 'M1', 3);
    gpaCourse($this->world, 'M2', 2);

    $registration = gpaTerm($this->world, $this->world['year'], Semester::FIRST, ['M1' => 'C+', 'M2' => 'D']);

    expect($this->calculator->forCourses($registration->courses()->with(['course', 'grade'])->get()))
        ->toMatchArray(['gpa' => round((3 * 2.7 + 2 * 2.0) / 5, 2), 'total_hours' => 5, 'earned_hours' => 5]);
});

it('blocks grade ranges that overlap another grade', function () {
    $this->actingAs(\App\Models\User::factory()->create(['is_super_admin' => true]))
        ->post(route('grades.store'), [
            'name' => 'X', 'order' => 20, 'points' => 3.5, 'min_score' => 88, 'max_score' => 91, 'counts_in_gpa' => 1,
        ])
        ->assertSessionHasErrors(['min_score']);

    expect(Grade::where('name', 'X')->exists())->toBeFalse();
});
