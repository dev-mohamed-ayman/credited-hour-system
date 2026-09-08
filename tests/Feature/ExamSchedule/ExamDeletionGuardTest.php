<?php

use App\Models\ExamSession;
use App\Services\ExamSeatingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a venue referenced by exam committees cannot be deleted via the guard', function () {
    $world = examWorld();
    $session = examService()->create(examAttributes($world), [
        ['venue_id' => $world['venue']->id, 'name' => 'لجنة 1', 'capacity' => 10],
    ]);

    expect($world['venue']->hasBlockingRelations())->toBeTrue();
    expect($world['venue']->getBlockingRelationsMessage())->toContain('لجان امتحانات');

    $session->committees()->delete();
    expect($world['venue']->fresh()->hasBlockingRelations())->toBeFalse();
});

test('a course with exam sessions cannot be deleted via the guard', function () {
    $world = examWorld();
    examService()->create(examAttributes($world));

    expect($world['courses']['E1']->hasBlockingRelations())->toBeTrue()
        ->and($world['courses']['E1']->getBlockingRelationsMessage())->toContain('جداول امتحانات');
});

test('a year with exam sessions cannot be deleted via the guard', function () {
    $world = examWorld();
    examService()->create(examAttributes($world));

    expect($world['year']->hasBlockingRelations())->toBeTrue()
        ->and($world['year']->getBlockingRelationsMessage())->toContain('جداول امتحانات');
});

test('a session with an active distribution cannot be deleted via the guard', function () {
    $world = examWorld();
    $session = examService()->create(examAttributes($world), [
        ['venue_id' => $world['venue']->id, 'name' => 'لجنة 1', 'capacity' => 10],
    ]);
    app(ExamSeatingService::class)->generateDistribution($session);

    expect($session->hasBlockingRelations())->toBeTrue()
        ->and($session->getBlockingRelationsMessage())->toContain('لجان امتحانات');
});

test('a session without committees or distribution can be deleted', function () {
    $world = examWorld();
    $session = examService()->create(examAttributes($world));

    expect($session->hasBlockingRelations())->toBeFalse();

    $session->delete();
    expect(ExamSession::count())->toBe(0);
});
