<?php

use App\Enums\RegistrationStatus;
use App\Enums\Semester;
use App\Enums\SemesterStatus;
use App\Enums\Student\StudentStatus;
use App\Enums\Student\StudyStatus;
use App\Enums\TermOutcome;
use App\Exceptions\TermClosureException;
use App\Models\Course;
use App\Models\Grade;
use App\Models\Level;
use App\Models\Registration;
use App\Models\RegistrationCourse;
use App\Models\Setting;
use App\Models\Student;
use App\Models\StudentTermRecord;
use App\Models\StudentWarning;
use App\Models\TermClosure;
use App\Models\Year;
use App\Services\TermClosureService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->world = billingWorld();
    seedResultScale();

    $this->world['level']->update(['promotion_hours' => 27]);
    $this->levels = collect([
        $this->world['level'],
        Level::create(['name' => 'الفرقة الثانية', 'promotion_hours' => 60]),
        Level::create(['name' => 'الفرقة الثالثة', 'promotion_hours' => 93]),
        Level::create(['name' => 'الفرقة الرابعة']),
    ]);

    Setting::query()->firstOrCreate([])->update(['warning_gpa_threshold' => 2.0, 'graduation_required_hours' => 132]);

    $this->year = $this->world['year'];
    $this->previousYear = Year::create([
        'year' => '2024-2025',
        'first_semester_status' => SemesterStatus::DISABLED,
        'second_semester_status' => SemesterStatus::DISABLED,
        'summer_semester_status' => SemesterStatus::DISABLED,
    ]);

    $this->service = app(TermClosureService::class);
    $this->student = $this->world['student'];
    $this->student->update(['status' => StudentStatus::REGISTERED->value, 'study_status' => StudyStatus::FRESHMAN->value, 'gender' => 'female']);
});

function closureStudent(array $world, string $code, array $attributes = []): Student
{
    $student = $world['student']->replicate();
    $student->forceFill([
        'username' => $code,
        'national_id' => '2990101'.substr($code, -7),
        'name' => 'طالب '.$code,
        ...$attributes,
    ])->save();

    return $student;
}

/**
 * Gives the student one course of $hours with $grade in the term.
 */
function earn(Student $student, Year $year, Semester $semester, int $hours, string $grade): RegistrationCourse
{
    $registration = Registration::firstOrCreate(
        ['student_id' => $student->id, 'year_id' => $year->id, 'semester' => $semester->value],
        ['status' => RegistrationStatus::APPROVED],
    );

    $course = Course::create([
        'code' => 'C'.uniqid(), 'name' => 'مادة', 'hours' => $hours, 'is_selected' => false, 'is_active' => true,
        'department_id' => $student->section->department_id, 'level_id' => $student->level_id, 'semester' => 'الأول',
    ]);

    return RegistrationCourse::create([
        'registration_id' => $registration->id,
        'course_id' => $course->id,
        'grade_id' => Grade::where('name', $grade)->value('id'),
    ]);
}

function academicWarnings(Student $student, int $count): void
{
    $closure = TermClosure::factory()->create(['year_id' => test()->previousYear->id, 'semester' => Semester::FIRST]);

    foreach (range(1, $count) as $i) {
        $student->warnings()->create(['term_closure_id' => $closure->id, 'type' => 'warning', 'reason' => 'سابق', 'is_active' => true]);
    }
}

function outcomeOf(Student $student, TermClosure $closure): StudentTermRecord
{
    return $closure->records()->where('student_id', $student->id)->firstOrFail();
}

describe('blockers', function () {
    it('refuses to close while a result is still Pending', function () {
        earn($this->student, $this->year, Semester::FIRST, 3, 'Pending');

        expect($this->service->blockers($this->year, Semester::FIRST))
            ->toContain('مازال هناك 1 نتيجة مادة غير مكتملة (Pending) في الترم.');

        $this->service->close($this->year, Semester::FIRST);
    })->throws(TermClosureException::class, 'Pending');

    it('ignores Pending courses of rejected registrations', function () {
        earn($this->student, $this->year, Semester::FIRST, 3, 'Pending')->registration->update(['status' => RegistrationStatus::REJECTED]);

        expect($this->service->blockers($this->year, Semester::FIRST))->toBe([]);
    });

    it('never closes the same term twice', function () {
        $this->service->close($this->year, Semester::FIRST);

        $this->service->close($this->year, Semester::FIRST);
    })->throws(TermClosureException::class, 'تم تنفيذ إجراءات نهاية هذا الترم من قبل.');

    it('needs promotion hours on every level but the last before the summer', function () {
        $this->levels[1]->update(['promotion_hours' => null]);

        expect($this->service->blockers($this->year, Semester::SUMMER))
            ->toBe(['لم يتم تحديد ساعات الترقية للفرق: الفرقة الثانية.'])
            ->and($this->service->blockers($this->year, Semester::FIRST))->toBe([]);
    });
});

describe('warnings after the first and second terms', function () {
    beforeEach(function () {
        earn($this->student, $this->previousYear, Semester::SECOND, 3, 'B');
    });

    it('warns a student under the GPA threshold', function () {
        earn($this->student, $this->year, Semester::FIRST, 9, 'F');

        $record = outcomeOf($this->student, $this->service->close($this->year, Semester::FIRST));

        expect($record->outcome)->toBe(TermOutcome::WARNED)
            ->and($record->warnings_after)->toBe(1)
            ->and($this->student->warnings()->academic()->where('is_active', true)->count())->toBe(1)
            ->and($this->student->fresh()->status)->toBe(StudentStatus::REGISTERED);
    });

    it('dismisses on the third active warning', function () {
        academicWarnings($this->student, 2);
        earn($this->student, $this->year, Semester::FIRST, 9, 'F');

        $record = outcomeOf($this->student, $this->service->close($this->year, Semester::FIRST));

        expect($record->outcome)->toBe(TermOutcome::DISMISSED)
            ->and($record->warnings_after)->toBe(3)
            ->and($this->student->fresh()->status)->toBe(StudentStatus::DISMISSED);
    });

    it('removes one warning after a good term', function () {
        academicWarnings($this->student, 2);
        earn($this->student, $this->year, Semester::FIRST, 9, 'A');

        $record = outcomeOf($this->student, $this->service->close($this->year, Semester::FIRST));

        expect($record->outcome)->toBe(TermOutcome::WARNING_REDUCED)
            ->and($record->warnings_after)->toBe(1)
            ->and($this->student->warnings()->academic()->where('is_active', true)->count())->toBe(1);
    });

    it('never warns a student with the exempt earned hours', function () {
        earn($this->student, $this->previousYear, Semester::FIRST, 105, 'D');
        earn($this->student, $this->year, Semester::FIRST, 60, 'F');

        expect(outcomeOf($this->student, $this->service->close($this->year, Semester::FIRST))->outcome)->toBe(TermOutcome::NO_CHANGE);
    });

    it('does not count a manual warning towards dismissal', function () {
        $this->student->warnings()->create(['type' => 'warning', 'reason' => 'يدوي', 'is_active' => true]);
        earn($this->student, $this->year, Semester::FIRST, 9, 'A');

        expect(outcomeOf($this->student, $this->service->close($this->year, Semester::FIRST))->outcome)->toBe(TermOutcome::NO_CHANGE);
    });

    it('skips excused and suspended students', function () {
        $this->student->update(['status' => StudentStatus::EXCUSED->value]);
        earn($this->student, $this->year, Semester::FIRST, 9, 'F');

        $record = outcomeOf($this->student, $this->service->close($this->year, Semester::FIRST));

        expect($record->outcome)->toBe(TermOutcome::EXCUSED)
            ->and($this->student->warnings()->count())->toBe(0);
    });

    it('leaves graduated, dismissed and withdrawn students out', function () {
        $graduate = closureStudent($this->world, 'GRAD0000001', ['status' => StudentStatus::GRADUATED->value]);

        expect($this->service->simulate($this->year, Semester::FIRST)->pluck('student.id'))
            ->toContain($this->student->id)
            ->not->toContain($graduate->id);
    });
});

it("never counts a student's first term", function () {
    $newcomer = closureStudent($this->world, 'NEW00000001', ['status' => StudentStatus::REGISTERED->value]);
    earn($newcomer, $this->year, Semester::FIRST, 9, 'F');

    expect(outcomeOf($newcomer, $this->service->close($this->year, Semester::FIRST))->outcome)->toBe(TermOutcome::FIRST_TERM);
});

it('counts the second term once the first was studied', function () {
    earn($this->student, $this->year, Semester::FIRST, 3, 'F');
    earn($this->student, $this->year, Semester::SECOND, 3, 'F');

    expect(outcomeOf($this->student, $this->service->close($this->year, Semester::SECOND))->outcome)->toBe(TermOutcome::WARNED);
});

describe('promotion after the summer', function () {
    it('promotes a student with the level hours to the next level as مستجد', function () {
        $this->student->update(['study_status' => StudyStatus::REMAINING->value]);
        earn($this->student, $this->year, Semester::FIRST, 27, 'B');

        $record = outcomeOf($this->student, $this->service->close($this->year, Semester::SUMMER));
        $student = $this->student->fresh();

        expect($record->outcome)->toBe(TermOutcome::PROMOTED)
            ->and($student->level_id)->toBe($this->levels[1]->id)
            ->and($student->study_status)->toBe(StudyStatus::FRESHMAN);
    });

    it('moves a مستجد who misses the hours to باقي', function () {
        earn($this->student, $this->year, Semester::FIRST, 26, 'B');

        $record = outcomeOf($this->student, $this->service->close($this->year, Semester::SUMMER));

        expect($record->outcome)->toBe(TermOutcome::HELD_BACK)
            ->and($record->note)->toBe('الساعات المكتسبة 26 أقل من 27')
            ->and($this->student->fresh()->study_status)->toBe(StudyStatus::REMAINING)
            ->and($this->student->fresh()->level_id)->toBe($this->levels[0]->id);
    });

    it('applies the chs rules to a student who misses the hours again', function (int $levelIndex, StudyStatus $studyStatus, int $earlierYears, TermOutcome $outcome, ?StudyStatus $studyStatusAfter) {
        $this->student->update(['level_id' => $this->levels[$levelIndex]->id, 'study_status' => $studyStatus->value]);

        for ($i = 1; $i <= $earlierYears; $i++) {
            $closure = TermClosure::factory()->create([
                'year_id' => Year::create(['year' => (2010 + $i).'-'.(2011 + $i)])->id,
                'semester' => Semester::SUMMER,
            ]);
            StudentTermRecord::create([
                'term_closure_id' => $closure->id, 'student_id' => $this->student->id, 'cgpa' => 1, 'earned_hours' => 0,
                'outcome' => TermOutcome::HELD_BACK->value, 'warnings_before' => 0, 'warnings_after' => 0,
                'level_id_before' => $this->levels[$levelIndex]->id,
            ]);
        }

        $record = outcomeOf($this->student, $this->service->close($this->year, Semester::SUMMER));

        expect($record->outcome)->toBe($outcome);

        if ($studyStatusAfter !== null) {
            expect($this->student->fresh()->study_status)->toBe($studyStatusAfter)
                ->and($this->student->fresh()->status)->toBe(StudentStatus::REGISTERED);
        } else {
            expect($this->student->fresh()->status)->toBe(StudentStatus::DISMISSED);
        }
    })->with([
        'باقي in the first level is dismissed' => [0, StudyStatus::REMAINING, 0, TermOutcome::DISMISSED, null],
        'باقي in the second level becomes من الخارج' => [1, StudyStatus::REMAINING, 0, TermOutcome::HELD_BACK, StudyStatus::EXTERNAL],
        'من الخارج in the second level is dismissed' => [1, StudyStatus::EXTERNAL, 0, TermOutcome::DISMISSED, null],
        'من الخارج in the third level stays من الخارج' => [2, StudyStatus::EXTERNAL, 3, TermOutcome::HELD_BACK, StudyStatus::EXTERNAL],
        'من الخارج in its fifth year in a level is dismissed' => [2, StudyStatus::EXTERNAL, 4, TermOutcome::DISMISSED, null],
    ]);

    it('graduates a student with the graduation hours', function () {
        $this->student->update(['level_id' => $this->levels[3]->id]);
        earn($this->student, $this->year, Semester::FIRST, 132, 'B');

        expect(outcomeOf($this->student, $this->service->close($this->year, Semester::SUMMER))->outcome)->toBe(TermOutcome::GRADUATED)
            ->and($this->student->fresh()->status)->toBe(StudentStatus::GRADUATED);
    });

    it('keeps a student without military education in the last level', function () {
        $this->levels[1]->update(['military_required_for_males' => true]);
        $this->student->update(['level_id' => $this->levels[3]->id, 'gender' => 'male', 'military_education_passed' => false]);
        earn($this->student, $this->year, Semester::FIRST, 132, 'B');

        $record = outcomeOf($this->student, $this->service->close($this->year, Semester::SUMMER));

        expect($record->outcome)->toBe(TermOutcome::HELD_BACK)
            ->and($record->note)->toBe('أكمل ساعات التخرج ولم يجتز التربية العسكرية')
            ->and($this->student->fresh()->level_id)->toBe($this->levels[3]->id)
            ->and($this->student->fresh()->study_status)->toBe(StudyStatus::REMAINING);
    });

    it('graduates a student with military education passed', function () {
        $this->levels[1]->update(['military_required_for_males' => true]);
        $this->student->update(['level_id' => $this->levels[3]->id, 'gender' => 'male', 'military_education_passed' => true]);
        earn($this->student, $this->year, Semester::FIRST, 132, 'B');

        expect(outcomeOf($this->student, $this->service->close($this->year, Semester::SUMMER))->outcome)->toBe(TermOutcome::GRADUATED);
    });

    it('clears every warning of a student back above the threshold', function () {
        academicWarnings($this->student, 2);
        earn($this->student, $this->year, Semester::FIRST, 27, 'B');

        $record = outcomeOf($this->student, $this->service->close($this->year, Semester::SUMMER));

        expect($record->warnings_after)->toBe(0)
            ->and($this->student->warnings()->where('is_active', true)->count())->toBe(0);
    });
});

it('simulates without changing anything', function () {
    earn($this->student, $this->year, Semester::FIRST, 27, 'B');

    $decision = $this->service->simulate($this->year, Semester::SUMMER)->firstWhere('student.id', $this->student->id);

    expect($decision['outcome'])->toBe(TermOutcome::PROMOTED)
        ->and($decision['level_after']->id)->toBe($this->levels[1]->id)
        ->and(TermClosure::count())->toBe(0)
        ->and($this->student->fresh()->level_id)->toBe($this->levels[0]->id);
});

describe('revert', function () {
    it('restores every student and lets the term be closed again', function () {
        academicWarnings($this->student, 1);
        earn($this->student, $this->year, Semester::FIRST, 27, 'B');
        $failing = closureStudent($this->world, 'FAIL0000001', ['status' => StudentStatus::REGISTERED->value, 'study_status' => StudyStatus::REMAINING->value]);

        $closure = $this->service->close($this->year, Semester::SUMMER);

        expect($failing->fresh()->status)->toBe(StudentStatus::DISMISSED)
            ->and($this->student->fresh()->level_id)->toBe($this->levels[1]->id);

        $this->service->revert($closure);

        expect($failing->fresh()->status)->toBe(StudentStatus::REGISTERED)
            ->and($failing->fresh()->study_status)->toBe(StudyStatus::REMAINING)
            ->and($this->student->fresh()->level_id)->toBe($this->levels[0]->id)
            ->and($this->student->fresh()->study_status)->toBe(StudyStatus::FRESHMAN)
            ->and($this->student->warnings()->where('is_active', true)->count())->toBe(1)
            ->and(TermClosure::whereKey($closure->id)->exists())->toBeFalse()
            ->and($this->service->blockers($this->year, Semester::SUMMER))->toBe([]);
    });

    it('deletes the warnings the closure issued', function () {
        earn($this->student, $this->previousYear, Semester::FIRST, 3, 'B');
        earn($this->student, $this->year, Semester::FIRST, 9, 'F');

        $closure = $this->service->close($this->year, Semester::FIRST);
        $this->service->revert($closure);

        expect(StudentWarning::count())->toBe(0);
    });

    it('only reverts the latest closure', function () {
        $first = $this->service->close($this->year, Semester::FIRST);
        $this->service->close($this->year, Semester::SECOND);

        $this->service->revert($first);
    })->throws(TermClosureException::class, 'لا يمكن التراجع إلا عن آخر ترم تم تنفيذ إجراءاته.');
});
