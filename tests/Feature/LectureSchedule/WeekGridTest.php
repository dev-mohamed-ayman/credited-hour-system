<?php

use App\Enums\DayOfWeek;
use App\Livewire\Admin\LectureSchedule\Index;
use App\Livewire\Admin\LectureSchedule\WeekGrid;
use App\Services\LectureScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function gridSession(array $world, array $overrides = [])
{
    return app(LectureScheduleService::class)->create(
        $world['course'],
        array_merge([
            'venue_id' => $world['venue']->id,
            'day' => DayOfWeek::SUNDAY,
            'start_time' => '09:00',
            'end_time' => '10:30',
            'section_numbers' => range(1, 3),
        ], $overrides),
    );
}

test('weekly grid shows each session in its day and time', function () {
    $world = schedulingWorld();

    gridSession($world);
    gridSession($world, [
        'day' => DayOfWeek::TUESDAY,
        'start_time' => '11:00',
        'end_time' => '12:30',
        'section_numbers' => range(4, 6),
    ]);

    Livewire::actingAs($world['admin'])
        ->test(WeekGrid::class, ['course' => $world['course']])
        ->assertSee('الأحد')
        ->assertSee('الثلاثاء')
        ->assertSee('مدرج أ')
        ->assertSee('09:00')
        ->assertSee('11:00')
        ->assertSee('سكاشن 1-3')
        ->assertSee('سكاشن 4-6');
});

test('lowering venue capacity flags scheduled sessions as over capacity without deleting them', function () {
    $world = schedulingWorld();
    seedSectionStudents(1, 200, $world);

    gridSession($world, ['section_numbers' => [1]]);

    $world['venue']->update(['capacity' => 100]);

    Livewire::actingAs($world['admin'])
        ->test(Index::class)
        ->set('course_id', $world['course']->id)
        ->assertSee('تجاوز السعة');

    expect(\App\Models\LectureSchedule::count())->toBe(1);
});

test('legacy sessions without section numbers are flagged', function () {
    $world = schedulingWorld();

    gridSession($world)->update(['section_numbers' => null]);

    Livewire::actingAs($world['admin'])
        ->test(Index::class)
        ->set('course_id', $world['course']->id)
        ->assertSee('لم تحدد السكاشن');
});
