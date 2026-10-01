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
        ['venue_id' => $world['venue']->id, 'day' => DayOfWeek::SUNDAY, 'start_time' => '09:00', 'end_time' => '10:30', 'section_numbers' => range(1, 2)],
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

test('valid session is created with its section range and success toast', function () {
    $world = schedulingWorld();

    Livewire::actingAs($world['admin'])
        ->test(Form::class, ['course' => $world['course']])
        ->set('venue_id', $world['venue']->id)
        ->set('day', DayOfWeek::SUNDAY->value)
        ->set('start_time', '09:00')
        ->set('end_time', '10:30')
        ->set('section_numbers', range(1, 3))
        ->call('save')
        ->assertDispatched('toast', ['message' => 'تم إضافة جلسة المحاضرة بنجاح', 'type' => 'success']);

    $this->assertDatabaseHas('lecture_schedules', [
        'course_id' => $world['course']->id,
        'venue_id' => $world['venue']->id,
        'day' => 'sunday',
        'start_time' => '09:00',
        'end_time' => '10:30',
    ]);

    expect(LectureSchedule::first()->section_numbers)->toBe([1, 2, 3]);
});

test('conflicting session is rejected with arabic toast and no row is created', function () {
    $world = schedulingWorld();

    $existing = app(\App\Services\LectureScheduleService::class)->create(
        $world['course'],
        ['venue_id' => $world['venue']->id, 'day' => DayOfWeek::SUNDAY, 'start_time' => '09:00', 'end_time' => '10:30', 'section_numbers' => range(1, 5)],
    );

    Livewire::actingAs($world['admin'])
        ->test(Form::class, ['course' => $world['course']])
        ->set('venue_id', $world['venue']->id)
        ->set('day', DayOfWeek::SUNDAY->value)
        ->set('start_time', '10:00')
        ->set('end_time', '11:30')
        ->set('section_numbers', range(6, 10))
        ->call('save')
        ->assertDispatched('toast', fn (string $name, array $params) => ($params[0]['type'] ?? null) === 'danger'
            && str_contains($params[0]['message'] ?? '', 'محجوز')
            && str_contains($params[0]['message'] ?? '', 'محاسبه'));

    expect(LectureSchedule::count())->toBe(1);
});

test('at least one section must be checked', function () {
    $world = schedulingWorld();

    Livewire::actingAs($world['admin'])
        ->test(Form::class, ['course' => $world['course']])
        ->set('venue_id', $world['venue']->id)
        ->set('day', DayOfWeek::SUNDAY->value)
        ->set('start_time', '09:00')
        ->set('end_time', '10:30')
        ->set('section_numbers', [])
        ->call('save')
        ->assertHasErrors(['section_numbers' => 'required']);

    expect(LectureSchedule::count())->toBe(0);
});

test('non-contiguous checked sections are saved as picked', function () {
    $world = schedulingWorld();

    Livewire::actingAs($world['admin'])
        ->test(Form::class, ['course' => $world['course']])
        ->set('venue_id', $world['venue']->id)
        ->set('day', DayOfWeek::SUNDAY->value)
        ->set('start_time', '09:00')
        ->set('end_time', '10:30')
        ->set('section_numbers', ['2', '5', '9'])
        ->call('save')
        ->assertHasNoErrors();

    expect(LectureSchedule::first()->section_numbers)->toBe([2, 5, 9]);
});

test('range shortcut merges with checked sections and normalizes reversed ranges', function () {
    $world = schedulingWorld();

    Livewire::actingAs($world['admin'])
        ->test(Form::class, ['course' => $world['course']])
        ->set('section_numbers', ['1', '11'])
        ->set('range_from', 6)
        ->set('range_to', 4)
        ->call('applyRange')
        ->assertSet('section_numbers', [1, 4, 5, 6, 11]);
});

test('select all and clear toggle every available section', function () {
    $world = schedulingWorld(4);

    Livewire::actingAs($world['admin'])
        ->test(Form::class, ['course' => $world['course']])
        ->call('selectAllSections')
        ->assertSet('section_numbers', [1, 2, 3, 4])
        ->call('clearSections')
        ->assertSet('section_numbers', []);
});

test('editing a session pre-checks its sections', function () {
    $world = schedulingWorld();

    $schedule = app(\App\Services\LectureScheduleService::class)->create(
        $world['course'],
        ['venue_id' => $world['venue']->id, 'day' => DayOfWeek::SUNDAY, 'start_time' => '09:00', 'end_time' => '10:30', 'section_numbers' => [3, 8]],
    );

    Livewire::actingAs($world['admin'])
        ->test(Form::class, ['schedule' => $schedule->fresh()])
        ->assertSet('section_numbers', [3, 8]);
});

test('form lists distributed section numbers with their student counts', function () {
    $world = schedulingWorld(3);
    seedSectionStudents(2, 4, $world);

    Livewire::actingAs($world['admin'])
        ->test(Form::class, ['course' => $world['course']])
        ->assertSee('3 سكشن متاح')
        ->assertSee('سكشن 2')
        ->assertSee('5 طالب');
});

test('form warns when students are not distributed yet', function () {
    $world = schedulingWorld(0);

    Livewire::actingAs($world['admin'])
        ->test(Form::class, ['course' => $world['course']])
        ->assertSee('لم يتم توزيع طلاب');
});

test('friday cannot be selected as a session day', function () {
    $world = schedulingWorld();

    Livewire::actingAs($world['admin'])
        ->test(Form::class, ['course' => $world['course']])
        ->set('venue_id', $world['venue']->id)
        ->set('day', 'friday')
        ->set('start_time', '09:00')
        ->set('end_time', '10:30')
        ->set('section_numbers', [1])
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
        ->set('section_numbers', [1])
        ->call('save')
        ->assertHasErrors(['end_time' => 'after']);
});

test('live total reflects the current selection against venue capacity', function () {
    $world = schedulingWorld();
    seedSectionStudents(1, 6, $world);

    Livewire::actingAs($world['admin'])
        ->test(Form::class, ['course' => $world['course']])
        ->set('venue_id', $world['venue']->id)
        ->set('section_numbers', [1])
        ->assertSee('الإجمالي المختار: ')
        ->assertSee('<strong>7</strong>', false)
        ->assertSee('السعة: <strong>300</strong>', false);
});

test('session delete removes the schedule', function () {
    $world = schedulingWorld();

    $schedule = app(\App\Services\LectureScheduleService::class)->create(
        $world['course'],
        ['venue_id' => $world['venue']->id, 'day' => DayOfWeek::SUNDAY, 'start_time' => '09:00', 'end_time' => '10:30', 'section_numbers' => range(1, 2)],
    );

    Livewire::actingAs($world['admin'])
        ->test(Index::class)
        ->set('course_id', $world['course']->id)
        ->call('deleteSession', $schedule->id)
        ->assertDispatched('toast');

    $this->assertDatabaseMissing('lecture_schedules', ['id' => $schedule->id]);
});
