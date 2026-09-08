<?php

use App\Exceptions\ExamScheduleException;
use App\Models\ExamSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/*
 * FR-010 / research R5 — concurrency guarantee, proven DETERMINISTICALLY.
 *
 * sqlite in-memory cannot reproduce a real OS-level race (writes are
 * serialized and lockForUpdate compiles to a no-op there), so we prove the
 * two properties that make the guarantee hold on MySQL:
 *   1. overlapping saves can never both succeed (sequential double-submit),
 *   2. the conflict check runs INSIDE the save transaction (check-then-act
 *      is closed atomically), asserted via DB::listen + transactionLevel().
 */

test('two overlapping saves cannot both succeed', function () {
    $world = examWorld();
    $service = examService();

    $service->create(examAttributes($world));

    expect(fn () => $service->create(examAttributes($world, [
        'course_id' => $world['courses']['E2']->id,
    ])))->toThrow(ExamScheduleException::class);

    expect(ExamSession::count())->toBe(1);
});

test('duplicate uniqueness saves cannot both succeed', function () {
    $world = examWorld();
    $service = examService();

    $service->create(examAttributes($world));

    expect(fn () => $service->create(examAttributes($world)))->toThrow(ExamScheduleException::class);

    expect(ExamSession::count())->toBe(1);
});

test('conflict queries execute inside the save transaction', function () {
    $world = examWorld();

    $transactionLevels = [];

    DB::listen(function ($query) use (&$transactionLevels) {
        if (str_contains($query->sql, 'exam_sessions')) {
            $transactionLevels[] = DB::transactionLevel();
        }
    });

    examService()->create(examAttributes($world));

    expect($transactionLevels)->not->toBeEmpty();
    expect(collect($transactionLevels)->every(fn (int $level) => $level >= 1))->toBeTrue();
});
