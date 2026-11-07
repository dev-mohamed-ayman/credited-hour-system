<?php

use App\Enums\RegistrationStatus;
use App\Enums\Semester;
use App\Enums\Student\ExcuseType;
use App\Enums\Student\StudentStatus;
use App\Exceptions\StudentExcuseException;
use App\Livewire\Admin\StudentAffairs\Excuses;
use App\Models\Course;
use App\Models\Grade;
use App\Models\Registration;
use App\Models\RegistrationCourse;
use App\Models\StudentExcuse;
use App\Models\TermClosure;
use App\Models\User;
use App\Services\StudentExcuseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->world = billingWorld();
    seedResultScale();

    $this->student = $this->world['student'];
    $this->student->update(['status' => StudentStatus::REGISTERED->value]);
    $this->year = $this->world['year'];
    $this->service = app(StudentExcuseService::class);

    $registration = Registration::create([
        'student_id' => $this->student->id, 'year_id' => $this->year->id,
        'semester' => Semester::FIRST->value, 'status' => RegistrationStatus::APPROVED,
    ]);

    $this->pending = Grade::pendingDefault();
    $this->enrollments = $this->world['courses']->map(fn (Course $course) => RegistrationCourse::create([
        'registration_id' => $registration->id, 'course_id' => $course->id, 'grade_id' => $this->pending->id,
    ]));
});

function gradeOf(RegistrationCourse $enrollment): string
{
    return $enrollment->fresh()->grade->name;
}

it('turns the chosen courses into IC for a course excuse', function () {
    $this->service->add($this->student, $this->year, Semester::FIRST, ExcuseType::COURSE, 'مرضي', [$this->enrollments[0]->id]);

    expect(gradeOf($this->enrollments[0]))->toBe('IC')
        ->and(gradeOf($this->enrollments[1]))->toBe('Pending')
        ->and($this->student->fresh()->status)->toBe(StudentStatus::REGISTERED);
});

it('turns every course of the term into IC and marks the student عذر for a term excuse', function () {
    $excuse = $this->service->add($this->student, $this->year, Semester::FIRST, ExcuseType::TERM, 'سفر');

    expect(gradeOf($this->enrollments[0]))->toBe('IC')
        ->and(gradeOf($this->enrollments[1]))->toBe('IC')
        ->and($excuse->excused_course_ids)->toHaveCount(2)
        ->and($this->student->fresh()->status)->toBe(StudentStatus::EXCUSED);
});

it('marks the student وقف قيد for a suspension recorded in the first term', function () {
    $this->service->add($this->student, $this->year, Semester::FIRST, ExcuseType::SUSPENSION, 'تجنيد');

    expect($this->student->fresh()->status)->toBe(StudentStatus::SUSPENDED);
});

it('refuses invalid excuses', function (Semester $semester, ExcuseType $type, string $message) {
    expect(fn () => $this->service->add($this->student, $this->year, $semester, $type, 'سبب'))
        ->toThrow(StudentExcuseException::class, $message);
})->with([
    'summer' => [Semester::SUMMER, ExcuseType::TERM, 'لا يمكن وضع عذر في الترم الصيفي.'],
    'suspension in the second term' => [Semester::SECOND, ExcuseType::SUSPENSION, 'وقف القيد يكون في الترم الأول فقط'],
    'course excuse without courses' => [Semester::FIRST, ExcuseType::COURSE, 'اختر مادة واحدة على الأقل.'],
]);

it('refuses to excuse a course that has a result', function () {
    $this->enrollments[1]->update(['grade_id' => Grade::where('name', 'B')->value('id')]);

    $this->service->add($this->student, $this->year, Semester::FIRST, ExcuseType::TERM, 'سفر');
})->throws(StudentExcuseException::class, 'لا يمكن وضع عذر لمادة لها نتيجة');

it('allows one excuse per term, and none beside a suspension', function () {
    $this->service->add($this->student, $this->year, Semester::FIRST, ExcuseType::SUSPENSION, 'تجنيد');

    $this->service->add($this->student, $this->year, Semester::SECOND, ExcuseType::TERM, 'سفر');
})->throws(StudentExcuseException::class, 'لا يمكن وضع أكثر من عذر للطالب في نفس الترم.');

it('refuses excuses once the term is closed', function () {
    TermClosure::factory()->create(['year_id' => $this->year->id, 'semester' => Semester::FIRST]);

    $this->service->add($this->student, $this->year, Semester::FIRST, ExcuseType::TERM, 'سفر');
})->throws(StudentExcuseException::class, 'تم تنفيذ إجراءات نهاية هذا الترم');

it('removes an excuse, putting its courses back to Pending and the student back to مقيد', function () {
    $excuse = $this->service->add($this->student, $this->year, Semester::FIRST, ExcuseType::TERM, 'سفر');

    $this->service->remove($excuse);

    expect(gradeOf($this->enrollments[0]))->toBe('Pending')
        ->and($this->student->fresh()->status)->toBe(StudentStatus::REGISTERED)
        ->and(StudentExcuse::count())->toBe(0);
});

it('keeps a suspension once any term of its year is closed', function () {
    $excuse = $this->service->add($this->student, $this->year, Semester::FIRST, ExcuseType::SUSPENSION, 'تجنيد');
    TermClosure::factory()->create(['year_id' => $this->year->id, 'semester' => Semester::SECOND]);

    $this->service->remove($excuse);
})->throws(StudentExcuseException::class, 'لا يمكن حذف العذر');

describe('screen', function () {
    beforeEach(function () {
        foreach (['student_excuses.view', 'student_excuses.create', 'student_excuses.delete'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo(['student_excuses.view', 'student_excuses.create', 'student_excuses.delete']);
    });

    it('needs the view permission', function () {
        $this->actingAs(User::factory()->create())->get(route('admin.student-affairs.excuses'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.student-affairs.excuses'))->assertSuccessful();
    });

    it('finds the student, records an excuse and lists it', function () {
        Livewire::actingAs($this->admin)
            ->test(Excuses::class)
            ->set('studentCode', $this->student->username)
            ->call('search')
            ->assertSee($this->student->name)
            ->assertSee('مادة CS101')
            ->set('yearId', $this->year->id)
            ->set('semester', Semester::FIRST->value)
            ->set('type', ExcuseType::COURSE->value)
            ->set('reason', 'عذر مرضي')
            ->set('courseIds', [$this->enrollments[0]->id])
            ->call('add')
            ->assertHasNoErrors()
            ->assertSee('عذر مرضي');

        expect(gradeOf($this->enrollments[0]))->toBe('IC');
    });

    it('reports a refused excuse', function () {
        Livewire::actingAs($this->admin)
            ->test(Excuses::class)
            ->set('studentCode', $this->student->username)
            ->call('search')
            ->set('yearId', $this->year->id)
            ->set('semester', Semester::SECOND->value)
            ->set('type', ExcuseType::SUSPENSION->value)
            ->set('reason', 'تجنيد')
            ->call('add')
            ->assertDispatched('toast', fn (string $name, array $params) => $params[0]['type'] === 'error');

        expect(StudentExcuse::count())->toBe(0);
    });

    it('deletes an excuse', function () {
        $excuse = $this->service->add($this->student, $this->year, Semester::FIRST, ExcuseType::TERM, 'سفر');

        Livewire::actingAs($this->admin)
            ->test(Excuses::class)
            ->set('studentCode', $this->student->username)
            ->call('search')
            ->call('remove', $excuse->id)
            ->assertSee('لا توجد أعذار مسجلة لهذا الطالب.');
    });

    it('reports an unknown student code', function () {
        Livewire::actingAs($this->admin)
            ->test(Excuses::class)
            ->set('studentCode', 'NOPE')
            ->call('search')
            ->assertHasErrors(['studentCode']);
    });
});
