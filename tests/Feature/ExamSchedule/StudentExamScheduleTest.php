<?php

use App\Livewire\Student\ExamSchedule;
use App\Services\ExamSeatingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function studentExamWorld(): array
{
    $world = examWorld();

    $session = examService()->create(examAttributes($world), [
        ['venue_id' => $world['venue']->id, 'name' => 'لجنة 1', 'capacity' => 10],
    ]);
    app(ExamSeatingService::class)->generateDistribution($session);
    examService()->publish($session);

    return $world + ['session' => $session];
}

test('a student sees nothing before any session is published', function () {
    $world = examWorld();

    Livewire::actingAs($world['students']->first(), 'student')
        ->test(ExamSchedule::class)
        ->assertDontSee('إحصاء');
});

test('a student sees the published exam with venue committee and seat number', function () {
    $world = studentExamWorld();
    $student = $world['students']->first();

    $assignment = $world['session']->seatAssignments()->where('student_id', $student->id)->firstOrFail();

    Livewire::actingAs($student, 'student')
        ->test(ExamSchedule::class)
        ->assertSee('إحصاء')
        ->assertSee('مدرج أ')
        ->assertSee('لجنة 1')
        ->assertSee($assignment->seat_number)
        ->assertSee('2026-01-15');
});

test('approved courses without a published session show not scheduled yet', function () {
    $world = studentExamWorld();

    Livewire::actingAs($world['students']->first(), 'student')
        ->test(ExamSchedule::class)
        ->assertSee('لم يُحدد بعد');
});

test('a student never sees another student seat assignment or unrelated exams', function () {
    $world = studentExamWorld();

    $outsider = \App\Models\Student::create([
        'name' => 'طالب خارج الجمهور',
        'certificate_type_id' => $world['students']->first()->certificate_type_id,
        'national_id' => '29901016000000',
        'username' => 'EXMOUT',
        'password' => bcrypt('password'),
        'plain_password' => 'password',
        'section_id' => $world['section']->id,
        'level_id' => $world['level']->id,
        'year_id' => $world['year']->id,
        'semester' => \App\Enums\Semester::FIRST->value,
    ]);

    Livewire::actingAs($outsider, 'student')
        ->test(ExamSchedule::class)
        ->assertDontSee('إحصاء');
});

test('the student exam schedule route renders for an authenticated student', function () {
    $world = studentExamWorld();

    $this->actingAs($world['students']->first(), 'student')
        ->get(route('student.exam-schedule'))
        ->assertSuccessful()
        ->assertSeeLivewire(ExamSchedule::class);
});

test('guests cannot access the student exam schedule route', function () {
    $this->get(route('student.exam-schedule'))->assertRedirect();
});
