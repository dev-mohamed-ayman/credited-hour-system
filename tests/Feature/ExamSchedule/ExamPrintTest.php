<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

test('the committee sheet shows its timetable and every member with seat numbers', function () {
    $world = examWorld();
    $committee = filledExamCommittee($world);
    examService()->createSession($committee, examSessionAttributes($world));

    $response = $this->actingAs($world['admin'])->get(route('exam-schedules.print.committee', $committee));

    $response->assertSuccessful()
        ->assertSee('لجنة 1')
        ->assertSee('مدرج أ')
        ->assertSee('إحصاء')
        ->assertSee('09:00 – 11:00');

    foreach ($world['students'] as $student) {
        $response->assertSee($student->name)->assertSee($student->username);
    }
});

test('the session sheet lists only members sitting that exam', function () {
    $world = examWorld();
    $committee = filledExamCommittee($world);
    examService()->addStudentsByCodes($committee, [$world['pendingStudent']->username]);
    $session = examService()->createSession($committee, examSessionAttributes($world));

    $this->actingAs($world['admin'])
        ->get(route('exam-schedules.print.session', $session))
        ->assertSuccessful()
        ->assertSee('كشف حضور')
        ->assertSee($world['students']->first()->name)
        ->assertDontSee($world['pendingStudent']->name);
});

test('the student schedule print shows the published committee exams', function () {
    $world = examWorld();
    $committee = filledExamCommittee($world);
    examService()->createSession($committee, examSessionAttributes($world));
    examService()->publish($committee->fresh());

    Permission::firstOrCreate(['name' => 'students.view', 'guard_name' => 'web']);
    $world['admin']->givePermissionTo('students.view');

    $this->actingAs($world['admin'])
        ->get(route('exam-schedules.print.student', $world['students']->first()))
        ->assertSuccessful()
        ->assertSee('إحصاء')
        ->assertSee('لجنة 1');
});

test('print sheets require the exam view permission', function () {
    $world = examWorld();
    $committee = examCommittee($world);

    $this->actingAs(User::factory()->create())
        ->get(route('exam-schedules.print.committee', $committee))
        ->assertForbidden();
});
