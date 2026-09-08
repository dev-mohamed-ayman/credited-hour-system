<?php

use App\Livewire\Admin\ExamSchedule\Form;
use App\Livewire\Admin\ExamSchedule\Seating;
use App\Models\ExamCommittee;
use App\Models\ExamSeatAssignment;
use App\Models\User;
use App\Services\ExamSeatingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('a permission-less user cannot mount the seating screen', function () {
    $world = examWorld();
    $session = examService()->create(examAttributes($world));
    $bare = User::factory()->create();

    Livewire::actingAs($bare)->test(Seating::class, ['session' => $session])->assertStatus(403);
});

test('committees are created through the session form', function () {
    $world = examWorld();

    Livewire::actingAs($world['admin'])
        ->test(Form::class, ['course' => $world['courses']['E1']])
        ->set('type', 'regular')
        ->set('exam_date', '2026-01-15')
        ->set('start_time', '09:00')
        ->set('end_time', '11:00')
        ->set('committees', [
            ['venue_id' => $world['venue']->id, 'name' => 'لجنة 1', 'capacity' => 3],
            ['venue_id' => $world['venue']->id, 'name' => 'لجنة 2', 'capacity' => 3],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseCount('exam_committees', 2);
});

test('duplicate committee names are rejected with an Arabic toast', function () {
    $world = examWorld();

    Livewire::actingAs($world['admin'])
        ->test(Form::class, ['course' => $world['courses']['E1']])
        ->set('type', 'regular')
        ->set('exam_date', '2026-01-15')
        ->set('start_time', '09:00')
        ->set('end_time', '11:00')
        ->set('committees', [
            ['venue_id' => $world['venue']->id, 'name' => 'لجنة 1', 'capacity' => 3],
            ['venue_id' => $world['venue']->id, 'name' => 'لجنة 1', 'capacity' => 4],
        ])
        ->call('save')
        ->assertDispatched('toast', fn (string $name, array $params) => ($params[0]['type'] ?? null) === 'danger'
            && str_contains($params[0]['message'] ?? '', 'مكرر'));

    expect(ExamCommittee::count())->toBe(0);
});

test('generate button creates the distribution with a success toast', function () {
    $world = examWorld();
    $session = examService()->create(examAttributes($world), [
        ['venue_id' => $world['venue']->id, 'name' => 'لجنة 1', 'capacity' => 3],
        ['venue_id' => $world['venue']->id, 'name' => 'لجنة 2', 'capacity' => 3],
    ]);

    Livewire::actingAs($world['admin'])
        ->test(Seating::class, ['session' => $session])
        ->call('generate')
        ->assertDispatched('toast', fn (string $name, array $params) => ($params[0]['type'] ?? null) === 'success');

    expect(ExamSeatAssignment::count())->toBe(5);
});

test('capacity overflow on generate shows the exact Arabic message and stores nothing', function () {
    $world = examWorld();
    $session = examService()->create(examAttributes($world), [
        ['venue_id' => $world['venue']->id, 'name' => 'لجنة 1', 'capacity' => 2],
    ]);

    Livewire::actingAs($world['admin'])
        ->test(Seating::class, ['session' => $session])
        ->call('generate')
        ->assertDispatched('toast', fn (string $name, array $params) => ($params[0]['type'] ?? null) === 'danger'
            && str_contains($params[0]['message'] ?? '', 'عدد الممتحنين (5) يتجاوز إجمالي سعة اللجان (2)'));

    expect(ExamSeatAssignment::count())->toBe(0);
});

test('the stale badge appears after a new approval and disappears after regeneration', function () {
    $world = examWorld();
    $session = examService()->create(examAttributes($world), [
        ['venue_id' => $world['venue']->id, 'name' => 'لجنة 1', 'capacity' => 10],
    ]);
    app(ExamSeatingService::class)->generateDistribution($session);

    $component = Livewire::actingAs($world['admin'])->test(Seating::class, ['session' => $session]);
    $component->assertDontSee('توزيع غير محدّث');

    $extra = \App\Models\Student::create([
        'name' => 'طالب طارئ',
        'certificate_type_id' => $world['students']->first()->certificate_type_id,
        'national_id' => '29901014000000',
        'username' => 'EXMURGENT',
        'password' => bcrypt('password'),
        'plain_password' => 'password',
        'section_id' => $world['section']->id,
        'level_id' => $world['level']->id,
        'year_id' => $world['year']->id,
        'semester' => \App\Enums\Semester::FIRST->value,
    ]);
    $registration = \App\Models\Registration::create([
        'student_id' => $extra->id,
        'year_id' => $world['year']->id,
        'semester' => \App\Enums\Semester::FIRST,
        'status' => \App\Enums\RegistrationStatus::APPROVED,
    ]);
    $registration->courses()->create([
        'course_id' => $world['courses']['E1']->id,
        'grade_id' => \App\Models\Grade::firstOrCreate(['name' => 'Pending'], ['is_pending_default' => true, 'order' => 0])->id,
    ]);

    $component->refresh()->assertSee('توزيع غير محدّث');
    $component->call('generate')->assertDontSee('توزيع غير محدّث');
});

test('a student can be moved between committees from the seating screen', function () {
    $world = examWorld();
    $session = examService()->create(examAttributes($world), [
        ['venue_id' => $world['venue']->id, 'name' => 'لجنة 1', 'capacity' => 3],
        ['venue_id' => $world['venue']->id, 'name' => 'لجنة 2', 'capacity' => 3],
    ]);
    app(ExamSeatingService::class)->generateDistribution($session);

    $first = $session->seatAssignments()->orderBy('id')->first();
    $targetCommittee = $session->committees()->where('name', 'لجنة 2')->firstOrFail();

    Livewire::actingAs($world['admin'])
        ->test(Seating::class, ['session' => $session])
        ->set("moveTarget.{$first->id}", $targetCommittee->id)
        ->call('move', $first->id)
        ->assertDispatched('toast', fn (string $name, array $params) => ($params[0]['type'] ?? null) === 'success');

    expect($first->fresh()->exam_committee_id)->toBe($targetCommittee->id);
});
