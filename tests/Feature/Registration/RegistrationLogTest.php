<?php

use App\Enums\RegistrationStatus;
use App\Enums\Semester;
use App\Livewire\Admin\RegistrationLog\Index;
use App\Models\Grade;
use App\Models\Registration;
use App\Models\RegistrationCourse;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->world = billingWorld();
});

function enrollInLog(Student $student, array $world, int $courseId, RegistrationStatus $status = RegistrationStatus::APPROVED, ?int $yearId = null): void
{
    $registration = Registration::create([
        'student_id' => $student->id,
        'year_id' => $yearId ?? $world['year']->id,
        'semester' => Semester::FIRST->value,
        'status' => $status,
    ]);

    RegistrationCourse::create([
        'registration_id' => $registration->id,
        'course_id' => $courseId,
        'grade_id' => Grade::pendingDefault()->id,
    ]);
}

function logStudent(array $world, string $code): Student
{
    $student = $world['student']->replicate();
    $student->forceFill(['username' => $code, 'national_id' => '2990101'.substr($code, -7), 'name' => 'طالب '.$code])->save();

    return $student;
}

it('lists the students registered in a course for the term with their count', function () {
    $course = $this->world['courses'][0];
    enrollInLog($this->world['student'], $this->world, $course->id);
    enrollInLog(logStudent($this->world, 'CS250002'), $this->world, $course->id, RegistrationStatus::PENDING);
    enrollInLog(logStudent($this->world, 'CS250003'), $this->world, $this->world['courses'][1]->id);
    enrollInLog(logStudent($this->world, 'CS250004'), $this->world, $course->id, RegistrationStatus::REJECTED);

    Livewire::actingAs($this->world['admin'])
        ->test(Index::class)
        ->set('yearId', $this->world['year']->id)
        ->set('semester', Semester::FIRST->value)
        ->set('courseId', $course->id)
        ->assertSee('عدد الطلاب المسجلين: 2')
        ->assertSee('CS250001')
        ->assertSee('CS250002')
        ->assertDontSee('CS250003')
        ->assertDontSee('CS250004');
});

it('asks for a course before showing anything', function () {
    Livewire::actingAs($this->world['admin'])
        ->test(Index::class)
        ->assertSee('اختر السنة والترم والمادة');
});

it('is reachable from the admin route and guarded by permission', function () {
    $this->actingAs($this->world['admin'])
        ->get(route('registration-log.index'))
        ->assertSuccessful();

    $this->actingAs(User::factory()->create())
        ->get(route('registration-log.index'))
        ->assertForbidden();
});
