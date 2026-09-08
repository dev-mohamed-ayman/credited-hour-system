<?php

use App\Enums\DayOfWeek;
use App\Exceptions\LectureScheduleConflictException;
use App\Livewire\Admin\LectureSchedule\Index;
use App\Livewire\Admin\LectureSchedule\WeekGrid;
use App\Services\LectureScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function gridSession(array $world, array $overrides = [], ?array $sectionIds = null)
{
    return app(LectureScheduleService::class)->create(
        $world['course'],
        array_merge([
            'venue_id' => $world['venue']->id,
            'day' => DayOfWeek::SUNDAY,
            'start_time' => '09:00',
            'end_time' => '10:30',
        ], $overrides),
        $sectionIds ?? $world['sections']->take(3)->pluck('id')->all(),
    );
}

test('weekly grid shows each session in its day and time', function () {
    $world = schedulingWorld();

    gridSession($world);
    gridSession($world, [
        'day' => DayOfWeek::TUESDAY,
        'start_time' => '11:00',
        'end_time' => '12:30',
    ], $world['sections']->slice(3, 3)->pluck('id')->all());

    Livewire::actingAs($world['admin'])
        ->test(WeekGrid::class, ['course' => $world['course']])
        ->assertSee('الأحد')
        ->assertSee('الثلاثاء')
        ->assertSee('مدرج أ')
        ->assertSee('09:00')
        ->assertSee('11:00');
});

test('lowering venue capacity flags scheduled sessions as over capacity without deleting them', function () {
    $world = schedulingWorld();
    seedSectionStudents($world['sections'][0], 200, $world);

    gridSession($world, [], [$world['sections'][0]->id]);

    $world['venue']->update(['capacity' => 100]);

    Livewire::actingAs($world['admin'])
        ->test(Index::class)
        ->set('course_id', $world['course']->id)
        ->assertSee('تجاوز السعة');

    expect(\App\Models\LectureSchedule::count())->toBe(1);
});

test('unlinked section is flagged as orphan and blocked from new sessions', function () {
    $world = schedulingWorld();
    $orphan = $world['sections'][0];

    gridSession($world, [], [$orphan->id]);

    $world['course']->sections()->detach($orphan->id);

    Livewire::actingAs($world['admin'])
        ->test(Index::class)
        ->set('course_id', $world['course']->id)
        ->assertSee('شعب غير مرتبطة');

    // The orphaned section cannot join a new session of this course.
    expect(fn () => gridSession($world, [
        'day' => DayOfWeek::MONDAY,
    ], [$orphan->id]))->toThrow(LectureScheduleConflictException::class, 'غير مرتبطة');
});
