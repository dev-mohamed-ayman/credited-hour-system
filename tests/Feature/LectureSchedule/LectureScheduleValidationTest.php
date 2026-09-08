<?php

use App\Enums\DayOfWeek;
use App\Exceptions\LectureScheduleConflictException;
use App\Models\LectureSchedule;
use App\Models\RegistrationFee;
use App\Models\Section;
use App\Models\Venue;
use App\Services\LectureScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function scheduleService(): LectureScheduleService
{
    return app(LectureScheduleService::class);
}

/**
 * @param  array<int, int>  $sectionIds
 */
function createSession(array $world, array $overrides = [], array $sectionIds = []): LectureSchedule
{
    $attributes = array_merge([
        'venue_id' => $world['venue']->id,
        'day' => DayOfWeek::SUNDAY,
        'start_time' => '09:00',
        'end_time' => '10:30',
    ], $overrides);

    return scheduleService()->create(
        $world['course'],
        $attributes,
        $sectionIds ?: $world['sections']->pluck('id')->all(),
    );
}

test('capacity overrun is rejected with the exact arabic message', function () {
    $world = schedulingWorld();
    seedSectionStudents($world['sections'][0], 30, $world);
    seedSectionStudents($world['sections'][1], 30, $world);

    $lab = Venue::factory()->lab()->create(['name' => 'معمل 1', 'capacity' => 40]);

    expect(fn () => createSession($world, ['venue_id' => $lab->id], [$world['sections'][0]->id, $world['sections'][1]->id]))
        ->toThrow(LectureScheduleConflictException::class, 'الإجمالي المختار 60 طالب يتجاوز سعة معمل (40)');

    expect(LectureSchedule::count())->toBe(0);
});

test('venue without capacity skips the capacity check', function () {
    $world = schedulingWorld();
    seedSectionStudents($world['sections'][0], 500, $world);

    $unknown = Venue::factory()->create(['name' => 'قاعة غير محددة', 'capacity' => null]);

    $schedule = createSession($world, ['venue_id' => $unknown->id], [$world['sections'][0]->id]);

    expect($schedule->exists)->toBeTrue();
});

test('zero-enrollment section falls back to configured students per section', function () {
    $world = schedulingWorld();

    RegistrationFee::create([
        'department_id' => $world['department']->id,
        'level_id' => $world['level']->id,
        'hour_payment' => 100,
        'ministerial_payment' => 500,
        'total_student_payment' => 2000,
        'number_of_students_per_section' => 25,
    ]);

    $lab = Venue::factory()->lab()->create(['name' => 'معمل 1', 'capacity' => 40]);

    // Two empty sections ⇒ 25 + 25 = 50 > 40 ⇒ rejected via the configured fallback.
    expect(fn () => createSession($world, ['venue_id' => $lab->id], [$world['sections'][0]->id, $world['sections'][1]->id]))
        ->toThrow(LectureScheduleConflictException::class, 'الإجمالي المختار 50 طالب يتجاوز سعة معمل (40)');
});

test('sections not linked to the course are rejected server-side', function () {
    $world = schedulingWorld();

    $foreign = Section::create(['name' => 'غريبة', 'department_id' => $world['department']->id]);

    expect(fn () => createSession($world, [], [$foreign->id]))
        ->toThrow(LectureScheduleConflictException::class, 'غير مرتبطة');
});

test('editing a session does not conflict with itself', function () {
    $world = schedulingWorld();
    $ids = $world['sections']->take(3)->pluck('id')->all();

    $schedule = createSession($world, [], $ids);

    // New slot overlaps the session's own previous slot — allowed via ignore-id.
    scheduleService()->update($schedule, [
        'venue_id' => $world['venue']->id,
        'day' => DayOfWeek::SUNDAY,
        'start_time' => '10:00',
        'end_time' => '11:30',
    ], $ids);

    expect($schedule->refresh()->start_time)->toBe('10:00');
});

test('editing a session into another session slot is rejected', function () {
    $world = schedulingWorld();

    $first = createSession($world, [], $world['sections']->take(2)->pluck('id')->all());
    $second = createSession($world, [
        'start_time' => '11:00',
        'end_time' => '12:30',
    ], $world['sections']->slice(2, 2)->pluck('id')->all());

    expect(fn () => scheduleService()->update($second, [
        'venue_id' => $world['venue']->id,
        'day' => DayOfWeek::SUNDAY,
        'start_time' => '09:00',
        'end_time' => '10:30',
    ], $world['sections']->slice(2, 2)->pluck('id')->all()))
        ->toThrow(LectureScheduleConflictException::class);
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
