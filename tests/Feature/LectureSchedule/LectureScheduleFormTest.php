<?php

use App\Enums\DayOfWeek;
use App\Livewire\Admin\LectureSchedule\Form;
use App\Livewire\Admin\LectureSchedule\Index;
use App\Models\LectureSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('guests are redirected away from scheduling routes', function (string $route) {
    $world = schedulingWorld();

    $this->get(route($route, $route === 'lecture-schedules.index' ? [] : $world['course']))
        ->assertRedirect(route('login'));
})->with(['lecture-schedules.index', 'lecture-schedules.create']);

test('staff without permissions get 403 on every scheduling route', function (string $route, bool $withCourse = false) {
    $world = schedulingWorld();
    $user = User::factory()->create();

    $params = $withCourse ? $world['course'] : [];
    $this->actingAs($user)->get(route($route, $params))->assertForbidden();
})->with([
    ['lecture-schedules.index', false],
    ['lecture-schedules.create', true],
]);

test('staff without edit permission gets 403 on the edit route', function () {
    $world = schedulingWorld();

    $schedule = app(\App\Services\LectureScheduleService::class)->create(
        $world['course'],
        ['venue_id' => $world['venue']->id, 'day' => DayOfWeek::SUNDAY, 'start_time' => '09:00', 'end_time' => '10:30'],
        $world['sections']->take(2)->pluck('id')->all(),
    );

    $bare = User::factory()->create();

    $this->actingAs($bare)->get(route('lecture-schedules.edit', $schedule))->assertForbidden();
});

test('a permission-less user cannot trigger save or delete actions', function () {
    $world = schedulingWorld();
    $bare = User::factory()->create();

    Livewire::actingAs($bare)
        ->test(Index::class)
        ->assertStatus(403);

    Livewire::actingAs($bare)
        ->test(Form::class, ['course' => $world['course']])
        ->assertStatus(403);
});

test('valid session is created with pivot rows and success toast', function () {
    $world = schedulingWorld();

    Livewire::actingAs($world['admin'])
        ->test(Form::class, ['course' => $world['course']])
        ->set('venue_id', $world['venue']->id)
        ->set('day', DayOfWeek::SUNDAY->value)
        ->set('start_time', '09:00')
        ->set('end_time', '10:30')
        ->set('section_ids', $world['sections']->take(3)->pluck('id')->all())
        ->call('save')
        ->assertDispatched('toast', ['message' => 'تم إضافة جلسة المحاضرة بنجاح', 'type' => 'success']);

    $this->assertDatabaseHas('lecture_schedules', [
        'course_id' => $world['course']->id,
        'venue_id' => $world['venue']->id,
        'day' => 'sunday',
        'start_time' => '09:00',
        'end_time' => '10:30',
    ]);

    $this->assertDatabaseCount('lecture_schedule_section', 3);
});

test('conflicting session is rejected with arabic toast and no row is created', function () {
    $world = schedulingWorld();

    $existing = app(\App\Services\LectureScheduleService::class)->create(
        $world['course'],
        ['venue_id' => $world['venue']->id, 'day' => DayOfWeek::SUNDAY, 'start_time' => '09:00', 'end_time' => '10:30'],
        $world['sections']->take(5)->pluck('id')->all(),
    );

    Livewire::actingAs($world['admin'])
        ->test(Form::class, ['course' => $world['course']])
        ->set('venue_id', $world['venue']->id)
        ->set('day', DayOfWeek::SUNDAY->value)
        ->set('start_time', '10:00')
        ->set('end_time', '11:30')
        ->set('section_ids', $world['sections']->slice(5, 5)->pluck('id')->all())
        ->call('save')
        ->assertDispatched('toast', fn (string $name, array $params) => ($params[0]['type'] ?? null) === 'danger'
            && str_contains($params[0]['message'] ?? '', 'محجوز')
            && str_contains($params[0]['message'] ?? '', 'محاسبه'));

    expect(LectureSchedule::count())->toBe(1);
});

test('range picker selects the right sections and normalizes reversed ranges', function () {
    $world = schedulingWorld();
    $ids = $world['sections']->pluck('id')->all();

    $component = Livewire::actingAs($world['admin'])
        ->test(Form::class, ['course' => $world['course']])
        ->set('range_from', $ids[0])
        ->set('range_to', $ids[9])
        ->call('applyRange');

    $component->assertSet('section_ids', array_slice($ids, 0, 10));

    $component->set('section_ids', [])
        ->set('range_from', $ids[9])
        ->set('range_to', $ids[0])
        ->call('applyRange')
        ->assertSet('section_ids', array_slice($ids, 0, 10));
});

test('duplicate picks from checkbox and range are deduplicated', function () {
    $world = schedulingWorld();
    $ids = $world['sections']->pluck('id')->all();

    Livewire::actingAs($world['admin'])
        ->test(Form::class, ['course' => $world['course']])
        ->set('section_ids', [$ids[0], $ids[1]])
        ->set('range_from', $ids[1])
        ->set('range_to', $ids[3])
        ->call('applyRange')
        ->assertSet('section_ids', [$ids[0], $ids[1], $ids[2], $ids[3]]);
});

test('friday cannot be selected as a session day', function () {
    $world = schedulingWorld();

    Livewire::actingAs($world['admin'])
        ->test(Form::class, ['course' => $world['course']])
        ->set('venue_id', $world['venue']->id)
        ->set('day', 'friday')
        ->set('start_time', '09:00')
        ->set('end_time', '10:30')
        ->set('section_ids', [$world['sections'][0]->id])
        ->call('save')
        ->assertHasErrors(['day']);
});

test('invalid time ranges produce arabic validation errors', function () {
    $world = schedulingWorld();

    Livewire::actingAs($world['admin'])
        ->test(Form::class, ['course' => $world['course']])
        ->set('venue_id', $world['venue']->id)
        ->set('day', DayOfWeek::SUNDAY->value)
        ->set('start_time', '11:00')
        ->set('end_time', '09:00')
        ->set('section_ids', [$world['sections'][0]->id])
        ->call('save')
        ->assertHasErrors(['end_time' => 'after']);
});

test('live total reflects the current selection against venue capacity', function () {
    $world = schedulingWorld();
    seedSectionStudents($world['sections'][0], 7, $world);

    Livewire::actingAs($world['admin'])
        ->test(Form::class, ['course' => $world['course']])
        ->set('venue_id', $world['venue']->id)
        ->set('section_ids', [$world['sections'][0]->id])
        ->assertSee('الإجمالي المختار: ')
        ->assertSee('<strong>7</strong>', false)
        ->assertSee('السعة: <strong>300</strong>', false);
});

test('session delete removes the schedule and its pivot rows', function () {
    $world = schedulingWorld();

    $schedule = app(\App\Services\LectureScheduleService::class)->create(
        $world['course'],
        ['venue_id' => $world['venue']->id, 'day' => DayOfWeek::SUNDAY, 'start_time' => '09:00', 'end_time' => '10:30'],
        $world['sections']->take(2)->pluck('id')->all(),
    );

    Livewire::actingAs($world['admin'])
        ->test(Index::class)
        ->set('course_id', $world['course']->id)
        ->call('deleteSession', $schedule->id)
        ->assertDispatched('toast');

    $this->assertDatabaseMissing('lecture_schedules', ['id' => $schedule->id]);
    $this->assertDatabaseCount('lecture_schedule_section', 0);
});
