<?php

use App\Enums\DayOfWeek;
use App\Exceptions\LectureScheduleConflictException;
use App\Models\LectureSchedule;
use App\Models\Venue;
use App\Services\LectureScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function scheduleService(): LectureScheduleService
{
    return app(LectureScheduleService::class);
}

function createSession(array $world, array $overrides = []): LectureSchedule
{
    return scheduleService()->create($world['course'], array_merge([
        'venue_id' => $world['venue']->id,
        'day' => DayOfWeek::SUNDAY,
        'start_time' => '09:00',
        'end_time' => '10:30',
        'section_numbers' => range(1, $world['sectionCount']),
    ], $overrides));
}

test('capacity overrun is rejected with the exact arabic message', function () {
    $world = schedulingWorld();
    seedSectionStudents(1, 29, $world);
    seedSectionStudents(2, 29, $world);

    $lab = Venue::factory()->lab()->create(['name' => 'معمل 1', 'capacity' => 40]);

    expect(fn () => createSession($world, ['venue_id' => $lab->id, 'section_numbers' => range(1, 2)]))
        ->toThrow(LectureScheduleConflictException::class, 'الإجمالي المختار 60 طالب يتجاوز سعة معمل (40)');

    expect(LectureSchedule::count())->toBe(0);
});

test('venue without capacity skips the capacity check', function () {
    $world = schedulingWorld();
    seedSectionStudents(1, 500, $world);

    $unknown = Venue::factory()->create(['name' => 'قاعة غير محددة', 'capacity' => null]);

    $schedule = createSession($world, ['venue_id' => $unknown->id, 'section_numbers' => [1]]);

    expect($schedule->exists)->toBeTrue();
});

test('scheduling is blocked until students are distributed on sections', function () {
    $world = schedulingWorld(0);

    expect(fn () => createSession($world, ['section_numbers' => [1]]))
        ->toThrow(LectureScheduleConflictException::class, 'لم يتم توزيع طلاب هذه الفرقة');
});

test('section range beyond the distributed sections is rejected', function () {
    $world = schedulingWorld(4);

    expect(fn () => createSession($world, ['section_numbers' => range(1, 5)]))
        ->toThrow(LectureScheduleConflictException::class, '4 سكشن فقط');
});

test('section numbers are stored sorted and deduplicated', function () {
    $world = schedulingWorld();

    $schedule = createSession($world, ['section_numbers' => ['7', 3, 5, 3, 1, 2]]);

    expect($schedule->fresh()->section_numbers)->toBe([1, 2, 3, 5, 7])
        ->and($schedule->sectionNumbersLabel())->toBe('سكاشن 1-3، 5، 7');
});

test('an empty section selection is rejected', function () {
    $world = schedulingWorld();

    expect(fn () => createSession($world, ['section_numbers' => []]))
        ->toThrow(LectureScheduleConflictException::class, 'يجب اختيار سكشن واحد على الأقل');
});

test('capacity counts only the checked sections', function () {
    $world = schedulingWorld(5);
    seedSectionStudents(2, 50, $world);
    seedSectionStudents(4, 50, $world);

    expect(scheduleService()->selectedStudentsCount($world['course'], [1, 3, 5]))->toBe(3)
        ->and(scheduleService()->selectedStudentsCount($world['course'], [2, 4]))->toBe(102);
});

test('editing a session does not conflict with itself', function () {
    $world = schedulingWorld();

    $schedule = createSession($world, ['section_numbers' => range(1, 3)]);

    // New slot overlaps the session's own previous slot — allowed via ignore-id.
    scheduleService()->update($schedule, [
        'venue_id' => $world['venue']->id,
        'day' => DayOfWeek::SUNDAY,
        'start_time' => '10:00',
        'end_time' => '11:30',
        'section_numbers' => range(1, 3),
    ]);

    expect($schedule->refresh()->start_time)->toBe('10:00');
});

test('editing a session into another session slot is rejected', function () {
    $world = schedulingWorld();

    createSession($world, ['section_numbers' => range(1, 2)]);
    $second = createSession($world, [
        'start_time' => '11:00',
        'end_time' => '12:30',
        'section_numbers' => range(3, 4),
    ]);

    expect(fn () => scheduleService()->update($second, [
        'venue_id' => $world['venue']->id,
        'day' => DayOfWeek::SUNDAY,
        'start_time' => '09:00',
        'end_time' => '10:30',
        'section_numbers' => range(3, 4),
    ]))->toThrow(LectureScheduleConflictException::class);
});

test('end time must be after start time', function () {
    $world = schedulingWorld();

    expect(fn () => createSession($world, ['start_time' => '10:30', 'end_time' => '10:30']))
        ->toThrow(LectureScheduleConflictException::class, 'يجب أن يكون وقت النهاية أكبر من وقت البداية');

    expect(fn () => createSession($world, ['start_time' => '11:00', 'end_time' => '09:00']))
        ->toThrow(LectureScheduleConflictException::class, 'يجب أن يكون وقت النهاية أكبر من وقت البداية');
});

test('times must land on quarter-hour boundaries', function () {
    $world = schedulingWorld();

    expect(fn () => createSession($world, ['start_time' => '09:07']))
        ->toThrow(LectureScheduleConflictException::class, 'ربع ساعة');

    expect(fn () => createSession($world, ['end_time' => '10:47']))
        ->toThrow(LectureScheduleConflictException::class, 'ربع ساعة');
});
