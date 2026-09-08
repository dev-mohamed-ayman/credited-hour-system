<?php

use App\Enums\DayOfWeek;
use App\Enums\SemesterStatus;
use App\Exceptions\LectureScheduleConflictException;
use App\Models\Course;
use App\Models\LectureSchedule;
use App\Models\Year;
use App\Services\LectureScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function conflictService(): LectureScheduleService
{
    return app(LectureScheduleService::class);
}

test('overlapping booking in the same venue is rejected naming the conflicting session', function () {
    $world = schedulingWorld();

    createSessionViaService($world, $world['sections']->take(5)->pluck('id')->all());

    $otherCourse = Course::create([
        'code' => 'ACC-B', 'name' => 'إحصاء', 'hours' => 3, 'is_selected' => false, 'is_active' => true,
        'department_id' => $world['department']->id, 'level_id' => $world['level']->id, 'semester' => 'الأول',
    ]);
    $otherCourse->sections()->attach($world['sections']->slice(5, 5)->pluck('id')->all());

    expect(fn () => conflictService()->create($otherCourse, [
        'venue_id' => $world['venue']->id,
        'day' => DayOfWeek::SUNDAY,
        'start_time' => '10:00',
        'end_time' => '11:30',
    ], $otherCourse->sections()->pluck('sections.id')->all()))
        ->toThrow(LectureScheduleConflictException::class, 'محاسبه');
});

test('back-to-back bookings in the same venue are allowed', function () {
    $world = schedulingWorld();

    createSessionViaService($world, $world['sections']->take(5)->pluck('id')->all());
    $second = createSessionViaService($world, $world['sections']->slice(5, 5)->pluck('id')->all(), [
        'start_time' => '10:30',
        'end_time' => '12:00',
    ]);

    expect($second->exists)->toBeTrue();
    expect(LectureSchedule::count())->toBe(2);
});

test('same venue same time on different days is allowed', function () {
    $world = schedulingWorld();

    createSessionViaService($world, $world['sections']->take(5)->pluck('id')->all());
    $second = createSessionViaService($world, $world['sections']->slice(5, 5)->pluck('id')->all(), [
        'day' => DayOfWeek::MONDAY,
    ]);

    expect($second->exists)->toBeTrue();
});

test('a section cannot attend two overlapping sessions of different courses', function () {
    $world = schedulingWorld();
    $otherVenue = \App\Models\Venue::factory()->create(['name' => 'مدرج ب', 'capacity' => 300]);

    createSessionViaService($world, [
        $world['sections'][2]->id,
    ]);

    $otherCourse = Course::create([
        'code' => 'ACC-C', 'name' => 'ضرائب', 'hours' => 3, 'is_selected' => false, 'is_active' => true,
        'department_id' => $world['department']->id, 'level_id' => $world['level']->id, 'semester' => 'الأول',
    ]);
    $otherCourse->sections()->attach($world['sections'][2]->id);

    // Different venue (no venue clash) but same day + overlapping time for section 3 ⇒ section conflict.
    expect(fn () => conflictService()->create($otherCourse, [
        'venue_id' => $otherVenue->id,
        'day' => DayOfWeek::SUNDAY,
        'start_time' => '10:00',
        'end_time' => '11:30',
    ], [$world['sections'][2]->id]))->toThrow(LectureScheduleConflictException::class, 'الشعبة');
});

test('same section in two sessions of the same course at different times is allowed', function () {
    $world = schedulingWorld();
    $ids = $world['sections']->take(3)->pluck('id')->all();

    createSessionViaService($world, $ids);
    $second = createSessionViaService($world, $ids, [
        'day' => DayOfWeek::TUESDAY,
        'start_time' => '11:00',
        'end_time' => '12:30',
    ]);

    expect($second->exists)->toBeTrue();
});

test('conflict checks are scoped to the same academic year', function () {
    $world = schedulingWorld();

    $otherYear = Year::create([
        'year' => '2026-2027',
        'first_semester_status' => SemesterStatus::OPEN_REGISTRATION,
        'second_semester_status' => SemesterStatus::DISABLED,
        'summer_semester_status' => SemesterStatus::DISABLED,
    ]);

    // Occupies the slot inside $otherYear (Year::current() is now the newest year).
    createSessionViaService($world, $world['sections']->take(5)->pluck('id')->all());

    // Validating the same slot against the FIRST year finds no conflict…
    conflictService()->validate(
        $world['course'],
        $world['venue'],
        DayOfWeek::SUNDAY,
        '09:00',
        '10:30',
        $world['sections']->slice(5, 5)->pluck('id')->all(),
        $world['year']->id,
    );

    // …while validating inside the same year as the booking is rejected.
    expect(fn () => conflictService()->validate(
        $world['course'],
        $world['venue'],
        DayOfWeek::SUNDAY,
        '09:00',
        '10:30',
        $world['sections']->slice(5, 5)->pluck('id')->all(),
        $otherYear->id,
    ))->toThrow(LectureScheduleConflictException::class);
});

test('conflict checks are scoped to the course semester', function () {
    $world = schedulingWorld();

    createSessionViaService($world, $world['sections']->take(5)->pluck('id')->all());

    $secondSemesterCourse = Course::create([
        'code' => 'ACC-D', 'name' => 'أحصاء ثاني', 'hours' => 3, 'is_selected' => false, 'is_active' => true,
        'department_id' => $world['department']->id, 'level_id' => $world['level']->id, 'semester' => 'الثاني',
    ]);
    $secondSemesterCourse->sections()->attach($world['sections']->slice(5, 5)->pluck('id')->all());

    $allowed = conflictService()->create($secondSemesterCourse, [
        'venue_id' => $world['venue']->id,
        'day' => DayOfWeek::SUNDAY,
        'start_time' => '09:00',
        'end_time' => '10:30',
    ], $world['sections']->slice(5, 5)->pluck('id')->all());

    expect($allowed->exists)->toBeTrue();
});

test('create stamps the year id from the active year', function () {
    $world = schedulingWorld();

    $schedule = createSessionViaService($world);

    expect($schedule->year_id)->toBe(Year::current()->id);
});

test('session created without an active year persists with null year and scopes only against null-year sessions', function () {
    $world = schedulingWorld();
    Year::query()->delete();

    $first = createSessionViaService($world, $world['sections']->take(5)->pluck('id')->all());
    expect($first->year_id)->toBeNull();

    // Same slot, null year again ⇒ conflict.
    expect(fn () => createSessionViaService($world, $world['sections']->slice(5, 5)->pluck('id')->all()))
        ->toThrow(LectureScheduleConflictException::class);

    // A session explicitly recorded in a year does not clash with the null-year one.
    $year = Year::create([
        'year' => '2026-2027',
        'first_semester_status' => SemesterStatus::OPEN_REGISTRATION,
        'second_semester_status' => SemesterStatus::DISABLED,
        'summer_semester_status' => SemesterStatus::DISABLED,
    ]);

    $scoped = createSessionViaService($world, $world['sections']->slice(5, 5)->pluck('id')->all());
    expect($scoped->year_id)->toBe($year->id)->and($scoped->exists)->toBeTrue();
});

/**
 * @param  array<int, int>  $sectionIds
 */
function createSessionViaService(array $world, ?array $sectionIds = null, array $overrides = []): LectureSchedule
{
    return app(LectureScheduleService::class)->create(
        $world['course'],
        array_merge([
            'venue_id' => $world['venue']->id,
            'day' => DayOfWeek::SUNDAY,
            'start_time' => '09:00',
            'end_time' => '10:30',
        ], $overrides),
        $sectionIds ?? $world['sections']->pluck('id')->all(),
    );
}
