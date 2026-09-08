<?php

use App\Enums\ExamType;
use App\Livewire\Admin\ExamSchedule\Form;
use App\Livewire\Admin\ExamSchedule\Index;
use App\Models\ExamSession;
use App\Models\User;
use App\Services\ExamScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('guests are redirected away from exam scheduling routes', function (string $route) {
    $world = examWorld();

    $this->get(route($route, $route === 'exam-schedules.index' ? [] : $world['courses']['E1']))
        ->assertRedirect(route('login'));
})->with(['exam-schedules.index', 'exam-schedules.create']);

test('staff without permissions get 403 on every exam scheduling route', function () {
    $world = examWorld();
    $bare = User::factory()->create();

    $session = app(ExamScheduleService::class)->create(examAttributes($world));

    $this->actingAs($bare)->get(route('exam-schedules.index'))->assertForbidden();
    $this->actingAs($bare)->get(route('exam-schedules.create', $world['courses']['E1']))->assertForbidden();
    $this->actingAs($bare)->get(route('exam-schedules.edit', $session))->assertForbidden();
    $this->actingAs($bare)->get(route('exam-schedules.seating', $session))->assertForbidden();
});

test('a permission-less user cannot mount the exam components', function () {
    $world = examWorld();
    $bare = User::factory()->create();

    Livewire::actingAs($bare)->test(Index::class)->assertStatus(403);
    Livewire::actingAs($bare)->test(Form::class, ['course' => $world['courses']['E1']])->assertStatus(403);
});

test('board lists only courses with approved registrations in the term', function () {
    $world = examWorld();
    $quiet = \App\Models\Course::create([
        'code' => 'Q-'.uniqid(), 'name' => 'مادة بلا تسجيلات', 'hours' => 2, 'is_selected' => false, 'is_active' => true,
        'department_id' => $world['department']->id, 'level_id' => $world['level']->id, 'semester' => 'الأول',
    ]);

    Livewire::actingAs($world['admin'])
        ->test(Index::class)
        ->assertSee('إحصاء')
        ->assertSee('جبر')
        ->assertDontSee($quiet->name);
});

test('board sorts by every contract option without error', function (string $sort) {
    $world = examWorld();
    examService()->create(examAttributes($world));

    Livewire::actingAs($world['admin'])
        ->test(Index::class)
        ->set('sort', $sort)
        ->assertOk()
        ->assertSee('إحصاء');
})->with(['name', 'examinees', 'exam_date', 'status']);

test('valid session is created with success toast', function () {
    $world = examWorld();

    Livewire::actingAs($world['admin'])
        ->test(Form::class, ['course' => $world['courses']['E1']])
        ->set('type', ExamType::REGULAR->value)
        ->set('exam_date', '2026-01-15')
        ->set('start_time', '09:00')
        ->set('end_time', '11:00')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast', fn (string $name, array $params) => ($params[0]['message'] ?? null) === 'تم إضافة جلسة الامتحان بنجاح'
            && ($params[0]['type'] ?? null) === 'success');

    $this->assertDatabaseHas('exam_sessions', [
        'course_id' => $world['courses']['E1']->id,
        'exam_date' => '2026-01-15',
        'status' => 'draft',
    ]);
});

test('a student conflict rejects with an Arabic toast and creates no row', function () {
    $world = examWorld();
    app(ExamScheduleService::class)->create(examAttributes($world));

    Livewire::actingAs($world['admin'])
        ->test(Form::class, ['course' => $world['courses']['E2']])
        ->set('type', ExamType::REGULAR->value)
        ->set('exam_date', '2026-01-15')
        ->set('start_time', '09:00')
        ->set('end_time', '11:00')
        ->call('save')
        ->assertDispatched('toast', fn (string $name, array $params) => ($params[0]['type'] ?? null) === 'danger'
            && str_contains($params[0]['message'] ?? '', 'طالب 001'));

    expect(ExamSession::where('course_id', $world['courses']['E2']->id)->exists())->toBeFalse();
});

test('editing a session keeps its course locked and updates fields', function () {
    $world = examWorld();
    $session = app(ExamScheduleService::class)->create(examAttributes($world));

    Livewire::actingAs($world['admin'])
        ->test(Form::class, ['session' => $session])
        ->set('exam_date', '2026-01-20')
        ->set('start_time', '12:00')
        ->set('end_time', '14:00')
        ->call('save')
        ->assertHasNoErrors();

    expect($session->fresh()->exam_date->toDateString())->toBe('2026-01-20');
});
