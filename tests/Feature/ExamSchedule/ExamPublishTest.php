<?php

use App\Enums\ExamSessionStatus;
use App\Exceptions\ExamScheduleException;
use App\Models\Course;
use App\Models\ExamSession;
use App\Models\Registration;
use App\Models\User;
use App\Services\ExamSeatingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function publishableWorld(): array
{
    $world = examWorld();

    $session = examService()->create(examAttributes($world), [
        ['venue_id' => $world['venue']->id, 'name' => 'لجنة 1', 'capacity' => 10],
    ]);

    app(ExamSeatingService::class)->generateDistribution($session);

    return $world + ['session' => $session];
}

test('a session with examinees and fresh seating publishes', function () {
    $world = publishableWorld();

    examService()->publish($world['session']);

    expect($world['session']->fresh()->status)->toBe(ExamSessionStatus::PUBLISHED);
});

test('publishing is refused for a session with zero examinees', function () {
    $world = examWorld();
    $quiet = Course::create([
        'code' => 'Q-'.uniqid(), 'name' => 'مادة بلا ممتحنين', 'hours' => 2, 'is_selected' => false, 'is_active' => true,
        'department_id' => $world['department']->id, 'level_id' => $world['level']->id, 'semester' => 'الأول',
    ]);

    $session = examService()->create(examAttributes($world, ['course_id' => $quiet->id]), [
        ['venue_id' => $world['venue']->id, 'name' => 'لجنة 1', 'capacity' => 10],
    ]);

    expect(fn () => examService()->publish($session))
        ->toThrow(ExamScheduleException::class, 'لا يوجد بها ممتحنون');
});

test('publishing is refused while the distribution is stale', function () {
    $world = publishableWorld();

    $pending = Registration::where('student_id', $world['pendingStudent']->id)->firstOrFail();
    $pending->update(['status' => \App\Enums\RegistrationStatus::APPROVED]);

    expect(fn () => examService()->publish($world['session']))
        ->toThrow(ExamScheduleException::class, 'أعد توليد التوزيع');
});

test('publishing is refused when a student conflict emerged after saving', function () {
    $world = examWorld();

    $e3 = Course::create([
        'code' => 'E3-'.uniqid(), 'name' => 'مادة ثالثة', 'hours' => 2, 'is_selected' => false, 'is_active' => true,
        'department_id' => $world['department']->id, 'level_id' => $world['level']->id, 'semester' => 'الأول',
    ]);

    $late = \App\Models\Student::create([
        'name' => 'طالب متأخر',
        'certificate_type_id' => $world['students']->first()->certificate_type_id,
        'national_id' => '29901015000000',
        'username' => 'EXMLATE',
        'password' => bcrypt('password'),
        'plain_password' => 'password',
        'section_id' => $world['section']->id,
        'level_id' => $world['level']->id,
        'year_id' => $world['year']->id,
        'semester' => \App\Enums\Semester::FIRST->value,
    ]);
    $grade = \App\Models\Grade::firstOrCreate(['name' => 'Pending'], ['is_pending_default' => true, 'order' => 0]);
    $registration = Registration::create([
        'student_id' => $late->id,
        'year_id' => $world['year']->id,
        'semester' => \App\Enums\Semester::FIRST,
        'status' => \App\Enums\RegistrationStatus::APPROVED,
    ]);
    $registration->courses()->create(['course_id' => $e3->id, 'grade_id' => $grade->id]);

    // Audiences are disjoint at save time, so both overlapping saves pass
    // (different venues — same venue would be a venue conflict).
    $venueB = \App\Models\Venue::factory()->create(['name' => 'مدرج ب', 'capacity' => 300]);

    $sessionE1 = examService()->create(examAttributes($world), [
        ['venue_id' => $world['venue']->id, 'name' => 'لجنة 1', 'capacity' => 10],
    ]);
    $sessionE3 = examService()->create(examAttributes($world, ['course_id' => $e3->id]), [
        ['venue_id' => $venueB->id, 'name' => 'لجنة 1', 'capacity' => 10],
    ]);

    app(ExamSeatingService::class)->generateDistribution($sessionE1);
    app(ExamSeatingService::class)->generateDistribution($sessionE3);

    examService()->publish($sessionE1);

    // The late student now also registers for E1 — a conflict emerges with no save.
    $registration->courses()->create(['course_id' => $world['courses']['E1']->id, 'grade_id' => $grade->id]);

    expect(fn () => examService()->publish($sessionE3->fresh()))
        ->toThrow(ExamScheduleException::class, 'تعارض');
});

test('publishing is refused when total capacity cannot hold the examinees', function () {
    $world = examWorld();

    $session = examService()->create(examAttributes($world), [
        ['venue_id' => $world['venue']->id, 'name' => 'لجنة 1', 'capacity' => 3],
    ]);

    expect(fn () => examService()->publish($session))
        ->toThrow(ExamScheduleException::class, 'عدد الممتحنين (5) يتجاوز إجمالي سعة اللجان (3)');
});

test('editing a published session automatically reverts it to draft', function () {
    $world = publishableWorld();
    examService()->publish($world['session']);

    examService()->update($world['session'], examAttributes($world, ['exam_date' => '2026-01-16']));

    expect($world['session']->fresh()->status)->toBe(ExamSessionStatus::DRAFT);
});

test('regenerating the distribution of a published session reverts it to draft', function () {
    $world = publishableWorld();
    examService()->publish($world['session']);

    app(ExamSeatingService::class)->generateDistribution($world['session']->fresh());

    expect($world['session']->fresh()->status)->toBe(ExamSessionStatus::DRAFT);
});

test('unpublishing returns the session to draft immediately', function () {
    $world = publishableWorld();
    examService()->publish($world['session']);

    examService()->unpublish($world['session']->fresh());

    expect($world['session']->fresh()->status)->toBe(ExamSessionStatus::DRAFT);
});

test('publish and unpublish actions require the publish permission', function () {
    $world = publishableWorld();

    $viewer = User::factory()->create();
    $viewer->givePermissionTo('exam_schedules.view');

    Livewire::actingAs($viewer)
        ->test(\App\Livewire\Admin\ExamSchedule\Index::class)
        ->call('publish', $world['session']->id)
        ->assertStatus(403);

    Livewire::actingAs($viewer)
        ->test(\App\Livewire\Admin\ExamSchedule\Index::class)
        ->call('unpublish', $world['session']->id)
        ->assertStatus(403);
});

test('index publish action publishes and toasts, refusals toast in arabic', function () {
    $world = publishableWorld();

    Livewire::actingAs($world['admin'])
        ->test(\App\Livewire\Admin\ExamSchedule\Index::class)
        ->call('publish', $world['session']->id)
        ->assertDispatched('toast', fn (string $name, array $params) => ($params[0]['type'] ?? null) === 'success');

    expect($world['session']->fresh()->status)->toBe(ExamSessionStatus::PUBLISHED);

    $quiet = Course::create([
        'code' => 'QQ-'.uniqid(), 'name' => 'مادة فارغة', 'hours' => 2, 'is_selected' => false, 'is_active' => true,
        'department_id' => $world['department']->id, 'level_id' => $world['level']->id, 'semester' => 'الأول',
    ]);
    $empty = examService()->create(examAttributes($world, [
        'course_id' => $quiet->id,
        'start_time' => '12:00',
        'end_time' => '14:00',
    ]), [
        ['venue_id' => $world['venue']->id, 'name' => 'لجنة 1', 'capacity' => 10],
    ]);

    Livewire::actingAs($world['admin'])
        ->test(\App\Livewire\Admin\ExamSchedule\Index::class)
        ->call('publish', $empty->id)
        ->assertDispatched('toast', fn (string $name, array $params) => ($params[0]['type'] ?? null) === 'danger'
            && str_contains($params[0]['message'] ?? '', 'لا يوجد بها ممتحنون'));

    expect(ExamSession::find($empty->id)->status)->toBe(ExamSessionStatus::DRAFT);
});
