<?php

use App\Enums\RegistrationStatus;
use App\Enums\Semester;
use App\Exceptions\ExamScheduleException;
use App\Livewire\Admin\StudentAffairs\SeatNumbers;
use App\Models\Course;
use App\Models\ExamCommittee;
use App\Models\ExamCommitteeStudent;
use App\Models\Grade;
use App\Models\Registration;
use App\Models\RegistrationCourse;
use App\Models\Setting;
use App\Services\ExamScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->world = billingWorld();
    $this->student = $this->world['student'];

    Permission::firstOrCreate(['name' => 'students.view', 'guard_name' => 'web']);
    $this->admin = $this->world['admin'];
    $this->admin->givePermissionTo('students.view');

    Setting::query()->firstOrCreate([])->update([
        'cert_show_grade' => true,
        'cert_show_cgpa' => true,
        'cert_show_seat_number' => true,
        'seat_show_seat_number' => true,
    ]);
});

function printsCommitteeSeat(array $world, \App\Models\Student $student, int $seat, string $name = 'لجنة 7'): ExamCommitteeStudent
{
    $committee = ExamCommittee::factory()->published()->create([
        'year_id' => $world['year']->id,
        'semester' => Semester::FIRST,
        'name' => $name,
    ]);

    return ExamCommitteeStudent::create(['exam_committee_id' => $committee->id, 'student_id' => $student->id, 'seat_number' => $seat]);
}

it('prints the cumulative GPA and its classification on the graduation certificate', function () {
    seedResultScale();

    $registration = Registration::create([
        'student_id' => $this->student->id, 'year_id' => $this->world['year']->id,
        'semester' => Semester::FIRST->value, 'status' => RegistrationStatus::APPROVED,
    ]);

    foreach (['CS101' => 'A', 'CS102' => 'B'] as $code => $grade) {
        RegistrationCourse::create([
            'registration_id' => $registration->id,
            'course_id' => Course::where('code', $code)->value('id'),
            'grade_id' => Grade::where('name', $grade)->value('id'),
        ]);
    }

    $this->actingAs($this->admin)
        ->post(route('print.certificates.print'), ['student_ids' => [$this->student->id]])
        ->assertSuccessful()
        ->assertSee('بتقدير عام')
        ->assertSee('جيد جداً')
        ->assertSee('٣.٣٥');
});

it('prints no classification when the student has no counted grades yet', function () {
    $this->actingAs($this->admin)
        ->post(route('print.certificates.print'), ['student_ids' => [$this->student->id]])
        ->assertSuccessful()
        ->assertDontSee('جيد جداً')
        ->assertSee('بنقاط تراكمية <span>---</span>', false);
});

it('prints the exam committee seat on the certificate, not the high school seat number', function () {
    $this->student->update(['seat_number' => 'HS-998877']);
    printsCommitteeSeat($this->world, $this->student, 4321);

    $this->actingAs($this->admin)
        ->post(route('print.certificates.print'), ['student_ids' => [$this->student->id]])
        ->assertSee('4321')
        ->assertSee('2025-2026')
        ->assertDontSee('HS-998877');
});

it('prints the current term committee seat on the admin seat cards', function () {
    $this->student->update(['seat_number' => 'HS-998877']);
    printsCommitteeSeat($this->world, $this->student, 1234);

    $this->actingAs($this->admin)
        ->post(route('print.seat.numbers.print'), ['student_ids' => [$this->student->id]])
        ->assertSuccessful()
        ->assertSee('1234')
        ->assertSee('لجنة 7')
        ->assertDontSee('HS-998877');
});

it('numbers the current term committee seats alphabetically from the start number', function () {
    $second = seatCardStudent($this->world, 'ياسر', 'CS250002');
    $first = seatCardStudent($this->world, 'أحمد', 'CS250003');
    $outsider = seatCardStudent($this->world, 'بدون لجنة', 'CS250004');

    $committee = ExamCommittee::factory()->create(['year_id' => $this->world['year']->id, 'semester' => Semester::FIRST]);
    foreach ([$this->student, $second, $first] as $index => $student) {
        $committee->members()->create(['student_id' => $student->id, 'seat_number' => $index + 1]);
    }

    Livewire::actingAs($this->admin)
        ->test(SeatNumbers::class)
        ->set('department_id', $this->world['department']->id)
        ->set('section_id', $this->world['section']->id)
        ->set('level_id', $this->world['level']->id)
        ->set('start_seat_number', 100001)
        ->call('generate')
        ->assertDispatched('toast', type: 'success');

    $seats = $committee->members()->pluck('seat_number', 'student_id');

    expect($seats[$first->id])->toBe(100001)
        ->and($seats[$this->student->id])->toBe(100002)
        ->and($seats[$second->id])->toBe(100003)
        ->and($outsider->fresh()->seat_number)->toBeNull();
});

it('refuses numbering that collides with another member of the same committee', function () {
    $other = seatCardStudent($this->world, 'طالب آخر', 'CS250009');

    $committee = ExamCommittee::factory()->create(['year_id' => $this->world['year']->id, 'semester' => Semester::FIRST]);
    $committee->members()->create(['student_id' => $this->student->id, 'seat_number' => 1]);
    $committee->members()->create(['student_id' => $other->id, 'seat_number' => 50]);

    app(ExamScheduleService::class)->numberSeats($this->world['year'], Semester::FIRST, [$this->student], 50);
})->throws(ExamScheduleException::class, 'رقم الجلوس 50 مستخدم بالفعل');
