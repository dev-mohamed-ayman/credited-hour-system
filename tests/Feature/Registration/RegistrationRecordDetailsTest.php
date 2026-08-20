<?php

use App\Enums\RegistrationStatus;
use App\Enums\Semester;
use App\Models\Registration;
use App\Models\RegistrationCourse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/** Registration with one course, ready to be viewed from any portal. */
function recordWorld(): array
{
    $world = billingWorld();
    $grade = App\Models\Grade::where('is_pending_default', true)->first();

    $registration = Registration::create([
        'student_id' => $world['student']->id,
        'year_id' => $world['year']->id,
        'semester' => Semester::FIRST->value,
        'status' => RegistrationStatus::PENDING,
    ]);

    RegistrationCourse::create([
        'registration_id' => $registration->id,
        'course_id' => $world['courses'][0]->id,
        'grade_id' => $grade->id,
    ]);

    return $world + ['registration' => $registration];
}

test('the student can open the details of a registration record', function () {
    ['student' => $student, 'registration' => $registration] = recordWorld();

    Livewire::actingAs($student, 'student')
        ->test(\App\Livewire\Student\RegistrationRecord\Index::class)
        ->assertOk()
        // the eye button must target the modal that actually exists on the page
        ->assertSeeHtml('data-bs-target="#showRegistrationModal'.$registration->id.'"')
        ->assertSeeHtml('id="showRegistrationModal'.$registration->id.'"')
        ->assertSee('تفاصيل التسجيل');
});

test('the admin details page opens and shows the registration', function () {
    ['admin' => $admin, 'registration' => $registration, 'courses' => $courses] = recordWorld();

    $this->actingAs($admin)
        ->get(route('registration-records.show', $registration->id))
        ->assertOk()
        ->assertSee($courses[0]->name);
});

test('the advisor details page opens for their own student', function () {
    ['advisor' => $advisor, 'student' => $student, 'registration' => $registration, 'courses' => $courses] = recordWorld();
    $student->update(['academic_advisor_id' => $advisor->id]);

    $this->actingAs($advisor, 'advisor')
        ->get(route('advisor.registration-records.show', $registration->id))
        ->assertOk()
        ->assertSee($courses[0]->name);
});

test('the admin records list links each row to a details page that resolves', function () {
    ['admin' => $admin, 'registration' => $registration] = recordWorld();

    Livewire::actingAs($admin)
        ->test(\App\Livewire\Admin\RegistrationRecord\Index::class)
        ->assertOk()
        ->assertSeeHtml(route('registration-records.show', $registration->id));
});

test('the advisor records list links each row to a details page that resolves', function () {
    ['advisor' => $advisor, 'student' => $student, 'registration' => $registration] = recordWorld();
    $student->update(['academic_advisor_id' => $advisor->id]);

    Livewire::actingAs($advisor, 'advisor')
        ->test(\App\Livewire\Advisor\RegistrationRecord\Index::class)
        ->assertOk()
        ->assertSeeHtml(route('advisor.registration-records.show', $registration->id));
});
