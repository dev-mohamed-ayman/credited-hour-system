<?php

use App\Enums\RegistrationStatus;
use App\Enums\Semester;
use App\Livewire\Student\Dashboard;
use App\Models\Grade;
use App\Models\Registration;
use App\Models\RegistrationCourse;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->world = billingWorld();
    $this->student = $this->world['student'];
});

function dashboardRegistration(array $world, RegistrationStatus $status, Semester $semester, string $courseCode): void
{
    $registration = Registration::create([
        'student_id' => $world['student']->id, 'year_id' => $world['year']->id,
        'semester' => $semester->value, 'status' => $status,
    ]);

    RegistrationCourse::create([
        'registration_id' => $registration->id,
        'course_id' => $world['courses']->firstWhere('code', $courseCode)->id,
        'grade_id' => Grade::where('is_pending_default', true)->value('id'),
    ]);
}

it('leaves rejected and cancelled registrations out of the registered hours', function () {
    dashboardRegistration($this->world, RegistrationStatus::APPROVED, Semester::FIRST, 'CS101');
    dashboardRegistration($this->world, RegistrationStatus::REJECTED, Semester::SECOND, 'CS102');
    dashboardRegistration($this->world, RegistrationStatus::CANCELLED, Semester::SUMMER, 'CS102');

    expect((new Dashboard)->calculateCumulativeStats($this->student)['registered_hours'])->toBe(3);
});

it('takes the graduation hours from the setting only', function () {
    Setting::query()->firstOrCreate([])->update(['graduation_required_hours' => 140]);

    expect((new Dashboard)->getGraduationRequiredHours())->toBe(140);
});
