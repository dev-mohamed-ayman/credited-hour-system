<?php

use App\Enums\ExamSessionStatus;
use App\Enums\Semester;
use App\Exceptions\ExamScheduleException;
use App\Models\Course;
use App\Models\ExamCommitteeStudent;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a committee is created as a draft for the term', function () {
    $world = examWorld();

    $committee = examCommittee($world);

    expect($committee->status)->toBe(ExamSessionStatus::DRAFT)
        ->and($committee->semester)->toBe(Semester::FIRST)
        ->and($committee->venue_id)->toBe($world['venue']->id);
});

test('committee names are unique within the same term', function () {
    $world = examWorld();
    examCommittee($world);

    expect(fn () => examCommittee($world))
        ->toThrow(ExamScheduleException::class, 'يوجد لجنة باسم');

    expect(examCommittee($world, ['semester' => Semester::SECOND->value])->exists)->toBeTrue();
});

test('an inactive venue cannot be chosen for a new committee', function () {
    $world = examWorld();
    $closed = Venue::factory()->create(['name' => 'مغلق', 'is_active' => false]);

    expect(fn () => examCommittee($world, ['venue_id' => $closed->id]))
        ->toThrow(ExamScheduleException::class, 'غير مفعّل');
});

test('students are added by code with sequential seat numbers', function () {
    $world = examWorld();
    $committee = examCommittee($world);

    $codes = $world['students']->take(3)->pluck('username')->all();
    $result = examService()->addStudentsByCodes($committee, $codes);

    expect($result['added'])->toBe($codes)
        ->and($result['errors'])->toBe([])
        ->and($committee->members()->orderBy('seat_number')->pluck('seat_number')->all())->toBe([1, 2, 3]);
});

test('codes are matched case-insensitively and trimmed', function () {
    $world = examWorld();
    $committee = examCommittee($world);
    $code = $world['students']->first()->username;

    $result = examService()->addStudentsByCodes($committee, ['  '.strtolower($code).'  ']);

    expect($result['added'])->toBe([$code]);
});

test('a batch keeps valid codes and reports unknown and duplicate ones', function () {
    $world = examWorld();
    $committee = examCommittee($world);
    $first = $world['students']->first()->username;
    examService()->addStudentsByCodes($committee, [$first]);

    $second = $world['students']->get(1)->username;
    $result = examService()->addStudentsByCodes($committee, [$first, 'NOPE-404', $second]);

    expect($result['added'])->toBe([$second])
        ->and($result['errors'])->toHaveKeys([$first, 'NOPE-404'])
        ->and($result['errors'][$first])->toContain('مضاف بالفعل لهذه اللجنة')
        ->and($result['errors']['NOPE-404'])->toContain('لا يوجد طالب');
});

test('a student belongs to only one committee per term', function () {
    $world = examWorld();
    $code = $world['students']->first()->username;

    examService()->addStudentsByCodes(examCommittee($world), [$code]);
    $result = examService()->addStudentsByCodes(examCommittee($world, ['name' => 'لجنة 2']), [$code]);

    expect($result['added'])->toBe([])
        ->and($result['errors'][$code])->toContain('"لجنة 1"');

    $otherTerm = examCommittee($world, ['semester' => Semester::SECOND->value]);
    expect(examService()->addStudentsByCodes($otherTerm, [$code])['added'])->toBe([$code]);
});

test('adding stops at the committee capacity', function () {
    $world = examWorld();
    $committee = examCommittee($world, ['capacity' => 2]);

    $result = examService()->addStudentsByCodes($committee, $world['students']->pluck('username')->all());

    expect($result['added'])->toHaveCount(2)
        ->and($result['errors'])->toHaveCount(3)
        ->and(collect($result['errors'])->first())->toContain('ممتلئة');
});

test('capacity cannot be lowered below the current member count', function () {
    $world = examWorld();
    $committee = filledExamCommittee($world);

    expect(fn () => examService()->updateCommittee($committee, ['capacity' => 3]))
        ->toThrow(ExamScheduleException::class, 'لا يمكن تقليل السعة');
});

test('removing a student frees their place', function () {
    $world = examWorld();
    $committee = filledExamCommittee($world);
    $student = $world['students']->first();

    examService()->removeStudent($committee, $student->id);

    expect($committee->members()->where('student_id', $student->id)->exists())->toBeFalse()
        ->and($committee->members()->count())->toBe(4);
});

test('an exam is scheduled for committee members registered in the course', function () {
    $world = examWorld();
    $committee = filledExamCommittee($world);

    $session = examService()->createSession($committee, examSessionAttributes($world));

    expect(examService()->examineeCount($session))->toBe(5)
        ->and(substr($session->fresh()->start_time, 0, 5))->toBe('09:00');
});

test('the pending student is never an examinee even when added to the committee', function () {
    $world = examWorld();
    $committee = filledExamCommittee($world);
    examService()->addStudentsByCodes($committee, [$world['pendingStudent']->username]);

    $session = examService()->createSession($committee, examSessionAttributes($world));

    expect(examService()->examineeCount($session))->toBe(5)
        ->and(examService()->examinees($session)->pluck('student_id'))->not->toContain($world['pendingStudent']->id);
});

test('an exam needs at least one committee member registered in the course', function () {
    $world = examWorld();
    $committee = examCommittee($world);

    expect(fn () => examService()->createSession($committee, examSessionAttributes($world)))
        ->toThrow(ExamScheduleException::class, 'لا يوجد طلاب في هذه اللجنة');
});

test('exam times must be quarter hours and end after start', function (string $start, string $end, string $message) {
    $world = examWorld();
    $committee = filledExamCommittee($world);

    expect(fn () => examService()->createSession($committee, examSessionAttributes($world, ['start_time' => $start, 'end_time' => $end])))
        ->toThrow(ExamScheduleException::class, $message);
})->with([
    'not a quarter' => ['09:10', '11:00', 'ربع ساعة'],
    'end before start' => ['11:00', '09:00', 'وقت النهاية أكبر'],
]);

test('the exam date must fall inside the configured exam window', function () {
    $world = examWorld();
    $world['year']->update(['first_semester_exam_from' => '2026-01-10', 'first_semester_exam_to' => '2026-01-25']);
    $committee = filledExamCommittee($world);

    expect(fn () => examService()->createSession($committee, examSessionAttributes($world, ['exam_date' => '2026-02-01'])))
        ->toThrow(ExamScheduleException::class, 'داخل فترة امتحانات');
});

test('a course is scheduled once per type inside a committee', function () {
    $world = examWorld();
    $committee = filledExamCommittee($world);
    examService()->createSession($committee, examSessionAttributes($world));

    expect(fn () => examService()->createSession($committee, examSessionAttributes($world, ['exam_date' => '2026-01-16'])))
        ->toThrow(ExamScheduleException::class, 'لها ميعاد امتحان');
});

test('the same course can run at different times in different committees', function () {
    $world = examWorld();
    $codes = $world['students']->pluck('username');

    $first = examCommittee($world);
    examService()->addStudentsByCodes($first, $codes->take(2)->all());

    $secondVenue = Venue::factory()->create(['name' => 'مدرج ب']);
    $second = examCommittee($world, ['name' => 'لجنة 2', 'venue_id' => $secondVenue->id]);
    examService()->addStudentsByCodes($second, $codes->slice(2)->all());

    examService()->createSession($first, examSessionAttributes($world));
    $later = examService()->createSession($second, examSessionAttributes($world, ['exam_date' => '2026-01-17', 'start_time' => '12:00', 'end_time' => '14:00']));

    expect(examService()->examineeCount($later))->toBe(3);
});

test('two committees in the same venue cannot overlap', function () {
    $world = examWorld();
    $codes = $world['students']->pluck('username');

    $first = examCommittee($world);
    examService()->addStudentsByCodes($first, $codes->take(2)->all());
    $second = examCommittee($world, ['name' => 'لجنة 2']);
    examService()->addStudentsByCodes($second, $codes->slice(2)->all());

    examService()->createSession($first, examSessionAttributes($world));

    expect(fn () => examService()->createSession($second, examSessionAttributes($world, ['start_time' => '10:00', 'end_time' => '12:00'])))
        ->toThrow(ExamScheduleException::class, 'محجوز');

    $backToBack = examService()->createSession($second, examSessionAttributes($world, ['start_time' => '11:00', 'end_time' => '13:00']));
    expect($backToBack->exists)->toBeTrue();
});

test('moving a committee to a busy venue is refused', function () {
    $world = examWorld();
    $codes = $world['students']->pluck('username');

    $first = examCommittee($world);
    examService()->addStudentsByCodes($first, $codes->take(2)->all());
    examService()->createSession($first, examSessionAttributes($world));

    $otherVenue = Venue::factory()->create(['name' => 'مدرج ب']);
    $second = examCommittee($world, ['name' => 'لجنة 2', 'venue_id' => $otherVenue->id]);
    examService()->addStudentsByCodes($second, $codes->slice(2)->all());
    examService()->createSession($second, examSessionAttributes($world));

    expect(fn () => examService()->updateCommittee($second, ['venue_id' => $world['venue']->id]))
        ->toThrow(ExamScheduleException::class, 'محجوز');
});

test('overlapping exams in one committee are refused for students registered in both', function () {
    $world = examWorld();
    $committee = filledExamCommittee($world);
    examService()->createSession($committee, examSessionAttributes($world));

    expect(fn () => examService()->createSession($committee, examSessionAttributes($world, [
        'course_id' => $world['courses']['E2']->id,
        'start_time' => '10:00',
        'end_time' => '12:00',
    ])))->toThrow(ExamScheduleException::class, 'تعارض في مواعيد الامتحانات');
});

test('overlapping exams in one committee are allowed when no student sits both', function () {
    $world = examWorld();
    $other = Course::create([
        'code' => 'E3-'.uniqid(), 'name' => 'مادة منفصلة', 'hours' => 2, 'is_selected' => false, 'is_active' => true,
        'department_id' => $world['department']->id, 'level_id' => $world['level']->id, 'semester' => 'الأول',
    ]);

    $loner = $world['pendingStudent'];
    \App\Models\Registration::where('student_id', $loner->id)->firstOrFail()->update(['status' => \App\Enums\RegistrationStatus::APPROVED]);
    $registration = \App\Models\Registration::where('student_id', $loner->id)->firstOrFail();
    $registration->courses()->delete();
    $registration->courses()->create(['course_id' => $other->id, 'grade_id' => \App\Models\Grade::first()->id]);

    $committee = filledExamCommittee($world);
    examService()->addStudentsByCodes($committee, [$loner->username]);
    examService()->createSession($committee, examSessionAttributes($world));

    $parallel = examService()->createSession($committee, examSessionAttributes($world, ['course_id' => $other->id]));

    expect($parallel->exists)->toBeTrue();
});

test('adding a student whose courses clash in the committee timetable is refused', function () {
    $world = examWorld();
    $codes = $world['students']->pluck('username');

    $committee = examCommittee($world);
    $registration = \App\Models\Registration::where('student_id', $world['students']->first()->id)->firstOrFail();
    $e2 = $registration->courses()->where('course_id', $world['courses']['E2']->id)->firstOrFail();
    $e2->delete();

    examService()->addStudentsByCodes($committee, [$codes->first()]);
    examService()->createSession($committee, examSessionAttributes($world));

    $other = \App\Models\Registration::where('student_id', $world['students']->get(1)->id)->firstOrFail();
    $other->courses()->where('course_id', $world['courses']['E1']->id)->delete();
    examService()->addStudentsByCodes($committee, [$codes->get(1)]);
    examService()->createSession($committee, examSessionAttributes($world, ['course_id' => $world['courses']['E2']->id, 'start_time' => '10:00', 'end_time' => '12:00']));

    $result = examService()->addStudentsByCodes($committee, [$codes->get(2)]);

    expect($result['added'])->toBe([])
        ->and($result['errors'][$codes->get(2)])->toContain('تعارض');
});

test('publishing requires members, exams and capacity', function () {
    $world = examWorld();
    $empty = examCommittee($world);

    expect(fn () => examService()->publish($empty))
        ->toThrow(ExamScheduleException::class, 'لا يوجد بها طلاب');

    $committee = filledExamCommittee($world, ['name' => 'لجنة 2', 'venue_id' => Venue::factory()->create()->id]);

    expect(fn () => examService()->publish($committee))
        ->toThrow(ExamScheduleException::class, 'بدون مواعيد');

    examService()->createSession($committee, examSessionAttributes($world));
    examService()->publish($committee->fresh());

    expect($committee->fresh()->status)->toBe(ExamSessionStatus::PUBLISHED);
});

test('any change to a published committee sends it back to draft', function (Closure $change) {
    $world = examWorld();
    $committee = filledExamCommittee($world);
    $session = examService()->createSession($committee, examSessionAttributes($world));
    examService()->publish($committee->fresh());

    $change($committee->fresh(), $session, $world);

    expect($committee->fresh()->status)->toBe(ExamSessionStatus::DRAFT);
})->with([
    'remove student' => [fn ($committee, $session, $world) => examService()->removeStudent($committee, $world['students']->first()->id)],
    'move exam' => [fn ($committee, $session) => examService()->updateSession($session, ['start_time' => '12:00', 'end_time' => '14:00'])],
    'delete exam' => [fn ($committee, $session) => examService()->deleteSession($session)],
    'rename committee' => [fn ($committee) => examService()->updateCommittee($committee, ['name' => 'لجنة معدلة'])],
]);

test('unassigned students are approved registrants outside every committee of the term', function () {
    $world = examWorld();
    $committee = examCommittee($world);
    examService()->addStudentsByCodes($committee, $world['students']->take(2)->pluck('username')->all());

    $unassigned = examService()->unassignedStudentsQuery($world['year']->id, Semester::FIRST)->pluck('id');

    expect($unassigned)->toHaveCount(3)
        ->not->toContain($world['pendingStudent']->id)
        ->not->toContain($world['students']->first()->id);
});

test('deleting a committee removes its members and exams', function () {
    $world = examWorld();
    $committee = filledExamCommittee($world);
    examService()->createSession($committee, examSessionAttributes($world));

    examService()->deleteCommittee($committee);

    expect(ExamCommitteeStudent::count())->toBe(0)
        ->and(\App\Models\ExamSession::count())->toBe(0);
});
