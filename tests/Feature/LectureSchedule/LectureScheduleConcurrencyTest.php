<?php

use App\Enums\DayOfWeek;
use App\Exceptions\LectureScheduleConflictException;
use App\Models\LectureSchedule;
use App\Services\LectureScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/*
 * FR-020 / research R4 — concurrency guarantee, proven DETERMINISTICALLY.
 *
 * NOTE: sqlite in-memory cannot reproduce a real OS-level race (writes are
 * serialized and `lockForUpdate` compiles to a no-op there), so instead of
 * pretending to spawn parallel processes we prove the two properties that
 * make the guarantee hold on MySQL:
 *   1. overlapping saves can never both succeed (sequential double-submit),
 *   2. the conflict check runs INSIDE the save transaction (check-then-act
 *      is closed atomically), asserted via DB::listen + transactionLevel().
 */

test('two overlapping saves cannot both succeed', function () {
    $world = schedulingWorld();
    $service = app(LectureScheduleService::class);

    $attributes = [
        'venue_id' => $world['venue']->id,
        'day' => DayOfWeek::SUNDAY,
        'start_time' => '09:00',
        'end_time' => '10:30',
    ];

    $service->create($world['course'], $attributes, $world['sections']->take(5)->pluck('id')->all());

    expect(fn () => $service->create($world['course'], $attributes, $world['sections']->slice(5, 5)->pluck('id')->all()))
        ->toThrow(LectureScheduleConflictException::class);

    expect(LectureSchedule::count())->toBe(1);
});

test('a stale pre-validation never bypasses the atomic save-time check', function () {
    $world = schedulingWorld();
    $service = app(LectureScheduleService::class);

    $attributes = [
        'venue_id' => $world['venue']->id,
        'day' => DayOfWeek::SUNDAY,
        'start_time' => '09:00',
        'end_time' => '10:30',
    ];

    // Simulate two requesters that both validate cleanly before either saves
    // (the check-then-act hazard): validate() alone passes twice.
    $service->validate($world['course'], $world['venue'], DayOfWeek::SUNDAY, '09:00', '10:30', $world['sections']->take(5)->pluck('id')->all(), $world['year']->id);
    $service->validate($world['course'], $world['venue'], DayOfWeek::SUNDAY, '09:00', '10:30', $world['sections']->slice(5, 5)->pluck('id')->all(), $world['year']->id);

    // create() re-validates atomically, so the second save still fails.
    $service->create($world['course'], $attributes, $world['sections']->take(5)->pluck('id')->all());

    expect(fn () => $service->create($world['course'], $attributes, $world['sections']->slice(5, 5)->pluck('id')->all()))
        ->toThrow(LectureScheduleConflictException::class);

    expect(LectureSchedule::count())->toBe(1);
});

test('conflict queries execute inside the save transaction', function () {
    $world = schedulingWorld();

    $transactionLevels = [];

    DB::listen(function ($query) use (&$transactionLevels) {
        if (str_contains($query->sql, 'lecture_schedules')) {
            $transactionLevels[] = DB::transactionLevel();
        }
    });

    app(LectureScheduleService::class)->create(
        $world['course'],
        [
            'venue_id' => $world['venue']->id,
            'day' => DayOfWeek::SUNDAY,
            'start_time' => '09:00',
            'end_time' => '10:30',
        ],
        $world['sections']->take(5)->pluck('id')->all(),
    );

    expect($transactionLevels)->not->toBeEmpty();
    expect(collect($transactionLevels)->every(fn (int $level) => $level >= 1))->toBeTrue();
});
