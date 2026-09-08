<?php

use App\Models\User;
use App\Services\ExamSeatingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function printedWorld(): array
{
    $world = examWorld();

    $session = examService()->create(examAttributes($world), [
        ['venue_id' => $world['venue']->id, 'name' => 'لجنة 1', 'capacity' => 10],
    ]);
    app(ExamSeatingService::class)->generateDistribution($session);
    examService()->publish($session);

    return $world + ['session' => $session];
}

test('the committee sheet lists exactly the placed students with their seat numbers', function () {
    $world = printedWorld();
    $committee = $world['session']->committees()->firstOrFail();

    $response = $this->actingAs($world['admin'])->get(route('exam-schedules.print.committee', $committee));

    $response->assertSuccessful();

    $assignments = $committee->assignments()->with('student')->get();

    foreach ($assignments as $assignment) {
        $response->assertSee($assignment->student->name)
            ->assertSee($assignment->seat_number);
    }

    $response->assertSee('لجنة 1')
        ->assertSee('مدرج أ')
        ->assertSee('إحصاء');
});

test('the committee sheet matches the students personal tables row for row', function () {
    $world = printedWorld();
    $committee = $world['session']->committees()->firstOrFail();

    $sheetNames = $world['students']
        ->map(fn ($student) => $student->name)
        ->all();

    $response = $this->actingAs($world['admin'])->get(route('exam-schedules.print.committee', $committee));

    foreach ($sheetNames as $name) {
        $response->assertSee($name);
    }

    expect($committee->assignments()->count())->toBe(count($sheetNames));
});

test('the committee sheet requires the exam view permission', function () {
    $world = printedWorld();
    $committee = $world['session']->committees()->firstOrFail();
    $bare = User::factory()->create();

    $this->actingAs($bare)->get(route('exam-schedules.print.committee', $committee))->assertForbidden();
});

test('staff can print a student published exam schedule', function () {
    $world = printedWorld();

    \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'students.view', 'guard_name' => 'web']);
    $staff = User::factory()->create();
    $staff->givePermissionTo('students.view');

    $this->actingAs($staff)
        ->get(route('exam-schedules.print.student', $world['students']->first()))
        ->assertSuccessful()
        ->assertSee('إحصاء')
        ->assertSee('لجنة 1');
});

test('the student schedule print hides unpublished sessions', function () {
    $world = examWorld();

    \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'students.view', 'guard_name' => 'web']);
    $staff = User::factory()->create();
    $staff->givePermissionTo('students.view');

    $this->actingAs($staff)
        ->get(route('exam-schedules.print.student', $world['students']->first()))
        ->assertSuccessful()
        ->assertDontSee('إحصاء');
});
