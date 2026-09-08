<?php

use App\Enums\ExamType;
use App\Enums\Semester;
use App\Exceptions\ExamScheduleException;
use App\Models\ExamSession;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('examinees are the approved registrations of the term including the course only', function () {
    $world = examWorld();
    $session = examService()->create(examAttributes($world));

    $examinees = examService()->examinees($session);

    expect($examinees)->toHaveCount(5)
        ->and($examinees->pluck('id'))->not->toContain($world['pendingStudent']->id);
});

test('soft-deleted students are excluded from the examinee audience', function () {
    $world = examWorld();
    $session = examService()->create(examAttributes($world));

    $world['students']->first()->delete();

    expect(examService()->examinees($session))->toHaveCount(4);
});

test('a shared student with an overlapping exam rejects the save naming students and courses', function () {
    $world = examWorld();
    examService()->create(examAttributes($world));

    expect(fn () => examService()->create(examAttributes($world, [
        'course_id' => $world['courses']['E2']->id,
    ])))->toThrow(ExamScheduleException::class, 'طالب 001');

    try {
        examService()->create(examAttributes($world, ['course_id' => $world['courses']['E2']->id]));
    } catch (ExamScheduleException $e) {
        expect($e->getMessage())->toContain('إحصاء')->toContain('جبر');
    }
});

test('back-to-back exams for the same student are allowed', function () {
    $world = examWorld();
    examService()->create(examAttributes($world));

    $second = examService()->create(examAttributes($world, [
        'course_id' => $world['courses']['E2']->id,
        'start_time' => '11:00',
        'end_time' => '13:00',
    ]));

    expect($second->exists)->toBeTrue();
});

test('same student same time on different dates is allowed', function () {
    $world = examWorld();
    examService()->create(examAttributes($world));

    $second = examService()->create(examAttributes($world, [
        'course_id' => $world['courses']['E2']->id,
        'exam_date' => '2026-01-16',
    ]));

    expect($second->exists)->toBeTrue();
});

test('conflict checks are scoped to the same year and semester', function () {
    $world = examWorld();
    examService()->create(examAttributes($world));

    $second = examService()->create(examAttributes($world, [
        'course_id' => $world['courses']['E2']->id,
        'semester' => Semester::SECOND->value,
    ]));

    expect($second->exists)->toBeTrue();
});

test('a second regular session for the same course+year+semester+type is rejected', function () {
    $world = examWorld();
    examService()->create(examAttributes($world));

    expect(fn () => examService()->create(examAttributes($world)))
        ->toThrow(ExamScheduleException::class, 'يوجد جلسة امتحان');
});

test('a resit session for the same course and term is allowed', function () {
    $world = examWorld();
    examService()->create(examAttributes($world));

    $resit = examService()->create(examAttributes($world, [
        'type' => ExamType::RESIT->value,
        'exam_date' => '2026-01-20',
    ]));

    expect($resit->exists)->toBeTrue();
    expect(ExamSession::count())->toBe(2);
});

test('end time must be after start time and on quarter-hour boundaries', function () {
    $world = examWorld();

    expect(fn () => examService()->create(examAttributes($world, ['end_time' => '09:00'])))
        ->toThrow(ExamScheduleException::class, 'يجب أن يكون وقت النهاية أكبر من وقت البداية');

    expect(fn () => examService()->create(examAttributes($world, ['start_time' => '09:07'])))
        ->toThrow(ExamScheduleException::class, 'ربع ساعة');
});

test('a configured exam window rejects dates outside it and names the range', function () {
    $world = examWorld();
    $world['year']->update([
        'first_semester_exam_from' => '2026-01-01',
        'first_semester_exam_to' => '2026-01-31',
    ]);

    expect(fn () => examService()->create(examAttributes($world, ['exam_date' => '2026-02-15'])))
        ->toThrow(ExamScheduleException::class, '2026-01-01');
});

test('an unset exam window accepts any date', function () {
    $world = examWorld();

    $session = examService()->create(examAttributes($world, ['exam_date' => '2027-06-01']));

    expect($session->exists)->toBeTrue();
});

test('two overlapping sessions in the same venue are rejected naming the session', function () {
    $world = examWorld();
    examService()->create(examAttributes($world), [
        ['venue_id' => $world['venue']->id, 'name' => 'لجنة 1', 'capacity' => 300],
    ]);

    expect(fn () => examService()->create(examAttributes($world, [
        'course_id' => $world['courses']['E2']->id,
        'start_time' => '10:30',
        'end_time' => '12:00',
    ]), [
        ['venue_id' => $world['venue']->id, 'name' => 'لجنة 1', 'capacity' => 300],
    ]))->toThrow(ExamScheduleException::class, 'مدرج أ');
});

test('non-overlapping sessions in the same venue are allowed', function () {
    $world = examWorld();
    examService()->create(examAttributes($world), [
        ['venue_id' => $world['venue']->id, 'name' => 'لجنة 1', 'capacity' => 300],
    ]);

    $second = examService()->create(examAttributes($world, [
        'course_id' => $world['courses']['E2']->id,
        'start_time' => '12:00',
        'end_time' => '14:00',
    ]), [
        ['venue_id' => $world['venue']->id, 'name' => 'لجنة 1', 'capacity' => 300],
    ]);

    expect($second->exists)->toBeTrue();
});

test('editing a session excludes it from its own conflict detection', function () {
    $world = examWorld();
    $session = examService()->create(examAttributes($world));

    examService()->update($session, examAttributes($world, ['notes' => 'ملاحظة']));
    expect($session->fresh()->notes)->toBe('ملاحظة');

    $other = examService()->create(examAttributes($world, [
        'course_id' => $world['courses']['E2']->id,
        'start_time' => '12:00',
        'end_time' => '14:00',
    ]));

    expect(fn () => examService()->update($other, examAttributes($world, [
        'course_id' => $world['courses']['E2']->id,
        'start_time' => '10:00',
        'end_time' => '11:00',
    ])))->toThrow(ExamScheduleException::class);
});
