<?php

use App\Enums\DayOfWeek;
use App\Livewire\Admin\LectureSchedule\Form;
use App\Models\User;
use App\Models\Venue;
use App\Services\LectureScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('venue can be created and appears in the index', function () {
    $world = schedulingWorld();

    $this->actingAs($world['admin'])->post(route('venues.store'), [
        'name' => 'مدرج ب',
        'type' => 'auditorium',
        'capacity' => 250,
        'is_active' => 1,
        'notes' => null,
    ])->assertRedirect(route('venues.index'));

    $this->assertDatabaseHas('venues', ['name' => 'مدرج ب', 'type' => 'auditorium', 'capacity' => 250]);

    $this->actingAs($world['admin'])->get(route('venues.index'))->assertSee('مدرج ب');
});

test('duplicate venue name is rejected with arabic message', function () {
    $world = schedulingWorld();

    $this->actingAs($world['admin'])->from(route('venues.create'))
        ->post(route('venues.store'), ['name' => 'مدرج أ', 'type' => 'lab'])
        ->assertSessionHasErrors(['name' => 'هذا الاسم مستخدم بالفعل']);

    expect(Venue::where('name', 'مدرج أ')->count())->toBe(1);
});

test('venue update enforces unique-except-self', function () {
    $world = schedulingWorld();
    $venue = Venue::factory()->create(['name' => 'قاعة ن']);

    $this->actingAs($world['admin'])->put(route('venues.update', $venue), [
        'name' => 'قاعة ن',
        'type' => 'classroom',
        'capacity' => 60,
        'is_active' => 1,
    ])->assertSessionHasNoErrors();

    expect($venue->refresh()->type->value)->toBe('classroom');
});

test('venue actions are permission gated per action', function () {
    $world = schedulingWorld();
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('venues.view');

    $venue = Venue::factory()->create();

    $this->actingAs($viewer)->get(route('venues.create'))->assertForbidden();
    $this->actingAs($viewer)->post(route('venues.store'), ['name' => 'x', 'type' => 'lab'])->assertForbidden();
    $this->actingAs($viewer)->delete(route('venues.destroy', $venue))->assertForbidden();
    $this->actingAs($viewer)->get(route('venues.index'))->assertOk();
});

test('deleting a venue referenced by sessions is blocked with guard message', function () {
    $world = schedulingWorld();

    app(LectureScheduleService::class)->create(
        $world['course'],
        ['venue_id' => $world['venue']->id, 'day' => DayOfWeek::SUNDAY, 'start_time' => '09:00', 'end_time' => '10:30'],
        $world['sections']->take(2)->pluck('id')->all(),
    );

    $this->actingAs($world['admin'])->delete(route('venues.destroy', $world['venue']))
        ->assertSessionHas('error', fn (string $message) => str_contains($message, 'لا يمكن الحذف'));

    $this->assertDatabaseHas('venues', ['id' => $world['venue']->id]);
});

test('deleting an unreferenced venue succeeds', function () {
    $world = schedulingWorld();
    $venue = Venue::factory()->create();

    $this->actingAs($world['admin'])->delete(route('venues.destroy', $venue));

    $this->assertDatabaseMissing('venues', ['id' => $venue->id]);
});

test('inactive venues are hidden from new sessions but kept when editing', function () {
    $world = schedulingWorld();
    $inactive = Venue::factory()->inactive()->create(['name' => 'مدرج مغلق', 'capacity' => 100]);

    // Create mode: inactive venue not offered.
    Livewire::actingAs($world['admin'])
        ->test(Form::class, ['course' => $world['course']])
        ->assertDontSee('مدرج مغلق');

    // A session already booked in the inactive venue keeps it selectable on edit.
    $schedule = app(LectureScheduleService::class)->create(
        $world['course'],
        ['venue_id' => $inactive->id, 'day' => DayOfWeek::SUNDAY, 'start_time' => '09:00', 'end_time' => '10:30'],
        $world['sections']->take(2)->pluck('id')->all(),
    );

    Livewire::actingAs($world['admin'])
        ->test(Form::class, ['schedule' => $schedule->fresh()])
        ->assertSee('مدرج مغلق');
});

test('saving a new session in an inactive venue is rejected server-side', function () {
    $world = schedulingWorld();
    $inactive = Venue::factory()->inactive()->create(['name' => 'مدرج مغلق']);

    Livewire::actingAs($world['admin'])
        ->test(Form::class, ['course' => $world['course']])
        ->set('venue_id', $inactive->id)
        ->set('day', DayOfWeek::SUNDAY->value)
        ->set('start_time', '09:00')
        ->set('end_time', '10:30')
        ->set('section_ids', [$world['sections'][0]->id])
        ->call('save')
        ->assertHasErrors(['venue_id']);
});
