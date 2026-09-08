<?php

use App\Exceptions\ExamScheduleException;
use App\Models\ExamCommittee;
use App\Models\ExamSeatAssignment;
use App\Services\ExamSeatingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function seatingService(): ExamSeatingService
{
    return app(ExamSeatingService::class);
}

function seatingWorld(int $students = 6, array $capacities = [3, 3]): array
{
    $world = examWorld($students);

    $session = examService()->create(examAttributes($world));

    $committees = collect($capacities)->map(fn ($capacity, $i) => ExamCommittee::create([
        'exam_session_id' => $session->id,
        'venue_id' => $world['venue']->id,
        'name' => 'لجنة '.($i + 1),
        'capacity' => $capacity,
    ]));

    return $world + compact('session', 'committees');
}

test('distribution splits examinees alphabetically across committees with unique seat numbers', function () {
    $world = seatingWorld();

    seatingService()->generateDistribution($world['session']);

    $assignments = $world['session']->seatAssignments()->with('student:id,name')->orderBy('id')->get();

    expect($assignments)->toHaveCount(6);

    $names = $assignments->pluck('student.name')->all();
    expect($names)->toBe(collect($names)->sort()->values()->all());

    $firstCommittee = $world['committees'][0]->assignments()->with('student')->get();
    expect($firstCommittee)->toHaveCount(3)
        ->and($firstCommittee->pluck('seat_number')->all())->toBe(['1', '2', '3'])
        ->and($firstCommittee->pluck('student.name')->all())
        ->toBe(['طالب 001', 'طالب 002', 'طالب 003']);
});

test('regenerating the same audience yields identical placements', function () {
    $world = seatingWorld();

    seatingService()->generateDistribution($world['session']);
    $before = $world['session']->seatAssignments()->orderBy('student_id')->pluck('seat_number', 'student_id')->all();

    seatingService()->generateDistribution($world['session']);
    $after = $world['session']->seatAssignments()->orderBy('student_id')->pluck('seat_number', 'student_id')->all();

    expect($after)->toBe($before);
});

test('regeneration keeps manual moves and never renumbers existing students', function () {
    $world = seatingWorld(6, [4, 4]);
    seatingService()->generateDistribution($world['session']);

    $moved = $world['session']->seatAssignments()->where('exam_committee_id', $world['committees'][0]->id)->first();
    seatingService()->moveStudent($moved, $world['committees'][1]);

    $moved->refresh();
    $movedSeat = $moved->seat_number;

    seatingService()->generateDistribution($world['session']);
    $moved->refresh();

    expect($moved->exam_committee_id)->toBe($world['committees'][1]->id)
        ->and($moved->seat_number)->toBe($movedSeat);
});

test('regeneration removes ineligible students and absorbs newcomers without disturbing others', function () {
    $world = seatingWorld(6, [4, 4]);
    seatingService()->generateDistribution($world['session']);

    $stable = $world['session']->seatAssignments()->pluck('seat_number', 'student_id')->all();

    $world['students']->first()->delete();

    $latecomer = \App\Models\Student::create([
        'name' => 'آ',
        'certificate_type_id' => $world['students']->first()->certificate_type_id,
        'national_id' => '29901012000000',
        'username' => 'EXMLATE',
        'password' => bcrypt('password'),
        'plain_password' => 'password',
        'section_id' => $world['section']->id,
        'level_id' => $world['level']->id,
        'year_id' => $world['year']->id,
        'semester' => \App\Enums\Semester::FIRST->value,
    ]);
    $registration = \App\Models\Registration::create([
        'student_id' => $latecomer->id,
        'year_id' => $world['year']->id,
        'semester' => \App\Enums\Semester::FIRST,
        'status' => \App\Enums\RegistrationStatus::APPROVED,
    ]);
    $registration->courses()->create(['course_id' => $world['courses']['E1']->id, 'grade_id' => \App\Models\Grade::firstOrCreate(['name' => 'Pending'], ['is_pending_default' => true, 'order' => 0])->id]);

    seatingService()->generateDistribution($world['session']);

    $after = $world['session']->seatAssignments()->pluck('seat_number', 'student_id')->all();

    expect($after)->not->toHaveKey($world['students']->first()->id)
        ->and($after)->toHaveKey($latecomer->id);

    foreach ($stable as $studentId => $seat) {
        if ($studentId === $world['students']->first()->id) {
            continue;
        }

        expect($after[$studentId])->toBe($seat);
    }
});

test('capacity overflow is rejected with the exact message and changes nothing', function () {
    $world = seatingWorld(5, [2, 2]);

    expect(fn () => seatingService()->generateDistribution($world['session']))
        ->toThrow(ExamScheduleException::class, 'عدد الممتحنين (5) يتجاوز إجمالي سعة اللجان (4)');

    expect(ExamSeatAssignment::count())->toBe(0);
});

test('seating becomes stale after a new approval and clears after regeneration', function () {
    $world = seatingWorld(6, [4, 4]);
    seatingService()->generateDistribution($world['session']);

    expect(seatingService()->isSeatingStale($world['session']))->toBeFalse();

    $extra = \App\Models\Student::create([
        'name' => 'طالب إضافي',
        'certificate_type_id' => $world['students']->first()->certificate_type_id,
        'national_id' => '29901013000000',
        'username' => 'EXMEXTRA',
        'password' => bcrypt('password'),
        'plain_password' => 'password',
        'section_id' => $world['section']->id,
        'level_id' => $world['level']->id,
        'year_id' => $world['year']->id,
        'semester' => \App\Enums\Semester::FIRST->value,
    ]);
    $registration = \App\Models\Registration::create([
        'student_id' => $extra->id,
        'year_id' => $world['year']->id,
        'semester' => \App\Enums\Semester::FIRST,
        'status' => \App\Enums\RegistrationStatus::APPROVED,
    ]);
    $registration->courses()->create([
        'course_id' => $world['courses']['E1']->id,
        'grade_id' => \App\Models\Grade::firstOrCreate(['name' => 'Pending'], ['is_pending_default' => true, 'order' => 0])->id,
    ]);

    expect(seatingService()->isSeatingStale($world['session']))->toBeTrue();

    seatingService()->generateDistribution($world['session']);

    expect(seatingService()->isSeatingStale($world['session']))->toBeFalse();
});

test('reducing a committee capacity below its placed students flags staleness without removing anyone', function () {
    $world = seatingWorld();
    seatingService()->generateDistribution($world['session']);

    $world['committees'][0]->update(['capacity' => 2]);

    expect(seatingService()->isSeatingStale($world['session']))->toBeTrue();
    expect(ExamSeatAssignment::count())->toBe(6);
});

test('moving a student into a full committee is rejected', function () {
    $world = seatingWorld(6, [3, 3]);
    seatingService()->generateDistribution($world['session']);

    $assignment = $world['session']->seatAssignments()
        ->where('exam_committee_id', $world['committees'][0]->id)
        ->first();

    expect(fn () => seatingService()->moveStudent($assignment, $world['committees'][1]))
        ->toThrow(ExamScheduleException::class, 'ممتلئة');
});
