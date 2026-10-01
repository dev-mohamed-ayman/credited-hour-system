<?php

use App\Livewire\Student\ExamSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function publishedExamCommittee(array $world): \App\Models\ExamCommittee
{
    $committee = filledExamCommittee($world);
    examService()->createSession($committee, examSessionAttributes($world));
    examService()->publish($committee->fresh());

    return $committee->fresh();
}

test('a student sees nothing while their committee is a draft', function () {
    $world = examWorld();
    $committee = filledExamCommittee($world);
    examService()->createSession($committee, examSessionAttributes($world));

    Livewire::actingAs($world['students']->first(), 'student')
        ->test(ExamSchedule::class)
        ->assertDontSee('إحصاء');
});

test('a student sees their committee exams with venue, committee and seat number', function () {
    $world = examWorld();
    $committee = publishedExamCommittee($world);
    $student = $world['students']->get(2);
    $seat = $committee->members()->where('student_id', $student->id)->value('seat_number');

    Livewire::actingAs($student, 'student')
        ->test(ExamSchedule::class)
        ->assertSee('إحصاء')
        ->assertSee('2026-01-15')
        ->assertSee('09:00 – 11:00')
        ->assertSee('مدرج أ')
        ->assertSee('لجنة 1')
        ->assertSee((string) $seat)
        ->assertSee('جبر — لم يُحدد بعد');
});

test('a student outside every committee sees no exams', function () {
    $world = examWorld();
    publishedExamCommittee($world);

    $outsider = $world['students']->first();
    examService()->removeStudent(\App\Models\ExamCommittee::firstOrFail(), $outsider->id);
    examService()->publish(\App\Models\ExamCommittee::firstOrFail());

    Livewire::actingAs($outsider, 'student')
        ->test(ExamSchedule::class)
        ->assertDontSee('إحصاء');
});
