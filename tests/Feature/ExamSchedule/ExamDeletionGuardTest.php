<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a venue used by an exam committee cannot be deleted via the guard', function () {
    $world = examWorld();
    $committee = examCommittee($world);

    expect($world['venue']->hasBlockingRelations())->toBeTrue()
        ->and($world['venue']->getBlockingRelationsMessage())->toContain('لجان امتحانات');

    examService()->deleteCommittee($committee);

    expect($world['venue']->fresh()->hasBlockingRelations())->toBeFalse();
});

test('a course with scheduled exams cannot be deleted via the guard', function () {
    $world = examWorld();
    examService()->createSession(filledExamCommittee($world), examSessionAttributes($world));

    expect($world['courses']['E1']->hasBlockingRelations())->toBeTrue()
        ->and($world['courses']['E1']->getBlockingRelationsMessage())->toContain('جداول امتحانات');
});

test('a year with exam committees cannot be deleted via the guard', function () {
    $world = examWorld();
    examCommittee($world);

    expect($world['year']->hasBlockingRelations())->toBeTrue()
        ->and($world['year']->getBlockingRelationsMessage())->toContain('لجان امتحانات');
});
