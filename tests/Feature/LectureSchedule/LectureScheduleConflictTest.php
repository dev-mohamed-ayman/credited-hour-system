<?php

use App\Enums\DayOfWeek;
use App\Enums\SemesterStatus;
use App\Exceptions\LectureScheduleConflictException;
use App\Models\Course;
use App\Models\Department;
use App\Models\LectureSchedule;
use App\Models\Section;
use App\Models\Year;
use App\Services\LectureScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function conflictService(): LectureScheduleService
{
    return app(LectureScheduleService::class);
}

function siblingCourse(array $world, string $code, string $name, string $semester = 'الأول'): Course
{
    return Course::create([
        'code' => $code, 'name' => $name, 'hours' => 3, 'is_selected' => false, 'is_active' => true,
        'department_id' => $world['department']->id, 'level_id' => $world['level']->id, 'semester' => $semester,
    ]);
}

test('overlapping booking in the same venue is rejected naming the conflicting session', function () {
    $world = schedulingWorld();

    createSessionViaService($world, 1, 5);

    $otherCourse = siblingCourse($world, 'ACC-B', 'إحصاء');

    expect(fn () => conflictService()->create($otherCourse, [
        'venue_id' => $world['venue']->id,
        'day' => DayOfWeek::SUNDAY,
        'start_time' => '10:00',
        'end_time' => '11:30',
        'section_numbers' => range(6, 10),
    ]))->toThrow(LectureScheduleConflictException::class, 'محاسبه');
});

test('back-to-back bookings in the same venue are allowed', function () {
    $world = schedulingWorld();

    createSessionViaService($world, 1, 5);
    $second = createSessionViaService($world, 6, 10, [
        'start_time' => '10:30',
        'end_time' => '12:00',
    ]);

    expect($second->exists)->toBeTrue();
    expect(LectureSchedule::count())->toBe(2);
});

test('same venue same time on different days is allowed', function () {
    $world = schedulingWorld();

    createSessionViaService($world, 1, 5);
    $second = createSessionViaService($world, 6, 10, [
        'day' => DayOfWeek::MONDAY,
    ]);

    expect($second->exists)->toBeTrue();
});

test('overlapping section ranges cannot attend two overlapping sessions of different courses', function () {
    $world = schedulingWorld();
    $otherVenue = \App\Models\Venue::factory()->create(['name' => 'مدرج ب', 'capacity' => 300]);

    createSessionViaService($world, 1, 6);

    $otherCourse = siblingCourse($world, 'ACC-C', 'ضرائب');

    // Different venue (no venue clash) but sections 5-6 overlap on the same day/time ⇒ section conflict.
    expect(fn () => conflictService()->create($otherCourse, [
        'venue_id' => $otherVenue->id,
        'day' => DayOfWeek::SUNDAY,
        'start_time' => '10:00',
        'end_time' => '11:30',
        'section_numbers' => range(5, 12),
    ]))->toThrow(LectureScheduleConflictException::class, 'سكاشن 5-6');
});

test('disjoint section ranges may attend overlapping sessions in different venues', function () {
    $world = schedulingWorld();
    $otherVenue = \App\Models\Venue::factory()->create(['name' => 'مدرج ب', 'capacity' => 300]);

    createSessionViaService($world, 1, 6);

    $allowed = conflictService()->create(siblingCourse($world, 'ACC-E', 'اقتصاد'), [
        'venue_id' => $otherVenue->id,
        'day' => DayOfWeek::SUNDAY,
        'start_time' => '09:00',
        'end_time' => '10:30',
        'section_numbers' => range(7, 12),
    ]);

    expect($allowed->exists)->toBeTrue();
});

test('same section numbers in another department never clash', function () {
    $world = schedulingWorld();
    $otherVenue = \App\Models\Venue::factory()->create(['name' => 'مدرج ب', 'capacity' => 300]);

    createSessionViaService($world, 1, 3);

    $otherDepartment = Department::create(['name' => 'إدارة أعمال', 'code' => 'BUS-'.uniqid()]);
    $otherWorld = array_merge($world, [
        'section' => Section::create(['name' => 'شعبة الإدارة', 'department_id' => $otherDepartment->id]),
    ]);
    seedSectionStudents(1, 3, $otherWorld);

    $otherCourse = siblingCourse($world, 'BUS-A', 'إدارة');
    $otherCourse->update(['department_id' => $otherDepartment->id]);

    $allowed = conflictService()->create($otherCourse, [
        'venue_id' => $otherVenue->id,
        'day' => DayOfWeek::SUNDAY,
        'start_time' => '09:00',
        'end_time' => '10:30',
        'section_numbers' => [1],
    ]);

    expect($allowed->exists)->toBeTrue()
        ->and(Department::count())->toBe(2);
});

test('non-contiguous sections sharing a number clash while disjoint picks do not', function () {
    $world = schedulingWorld();
    $otherVenue = \App\Models\Venue::factory()->create(['name' => 'مدرج ب', 'capacity' => 300]);

    conflictService()->create($world['course'], [
        'venue_id' => $world['venue']->id,
        'day' => DayOfWeek::SUNDAY,
        'start_time' => '09:00',
        'end_time' => '10:30',
        'section_numbers' => [1, 3, 5],
    ]);

    $otherCourse = siblingCourse($world, 'ACC-F', 'مراجعة');
    $attributes = [
        'venue_id' => $otherVenue->id,
        'day' => DayOfWeek::SUNDAY,
        'start_time' => '09:00',
        'end_time' => '10:30',
    ];

    expect(fn () => conflictService()->create($otherCourse, $attributes + ['section_numbers' => [4, 5]]))
        ->toThrow(LectureScheduleConflictException::class, 'سكشن 5');

    expect(conflictService()->create($otherCourse, $attributes + ['section_numbers' => [2, 4, 6]])->exists)->toBeTrue();
});

test('same sections in two sessions of the same course at different times is allowed', function () {
    $world = schedulingWorld();

    createSessionViaService($world, 1, 3);
    $second = createSessionViaService($world, 1, 3, [
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
    createSessionViaService($world, 1, 5);

    // Validating the same slot against the FIRST year finds no conflict…
    conflictService()->validate($world['course'], $world['venue'], DayOfWeek::SUNDAY, '09:00', '10:30', range(6, 10), $world['year']->id);

    // …while validating inside the same year as the booking is rejected.
    expect(fn () => conflictService()->validate($world['course'], $world['venue'], DayOfWeek::SUNDAY, '09:00', '10:30', range(6, 10), $otherYear->id))
        ->toThrow(LectureScheduleConflictException::class);
});

test('conflict checks are scoped to the course semester', function () {
    $world = schedulingWorld();

    createSessionViaService($world, 1, 5);

    $allowed = conflictService()->create(siblingCourse($world, 'ACC-D', 'أحصاء ثاني', 'الثاني'), [
        'venue_id' => $world['venue']->id,
        'day' => DayOfWeek::SUNDAY,
        'start_time' => '09:00',
        'end_time' => '10:30',
        'section_numbers' => range(1, 5),
    ]);

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

    $first = createSessionViaService($world, 1, 5);
    expect($first->year_id)->toBeNull();

    // Same slot, null year again ⇒ conflict.
    expect(fn () => createSessionViaService($world, 6, 10))
        ->toThrow(LectureScheduleConflictException::class);

    // A session explicitly recorded in a year does not clash with the null-year one.
    $year = Year::create([
        'year' => '2026-2027',
        'first_semester_status' => SemesterStatus::OPEN_REGISTRATION,
        'second_semester_status' => SemesterStatus::DISABLED,
        'summer_semester_status' => SemesterStatus::DISABLED,
    ]);

    $scoped = createSessionViaService($world, 6, 10);
    expect($scoped->year_id)->toBe($year->id)->and($scoped->exists)->toBeTrue();
});

function createSessionViaService(array $world, int $sectionFrom = 1, ?int $sectionTo = null, array $overrides = []): LectureSchedule
{
    return app(LectureScheduleService::class)->create(
        $world['course'],
        array_merge([
            'venue_id' => $world['venue']->id,
            'day' => DayOfWeek::SUNDAY,
            'start_time' => '09:00',
            'end_time' => '10:30',
            'section_numbers' => range($sectionFrom, $sectionTo ?? $world['sectionCount']),
        ], $overrides),
    );
}
