<?php

use App\Enums\RegistrationStatus;
use App\Enums\ResultChangeSource;
use App\Enums\Semester;
use App\Enums\Student\ExcuseType;
use App\Livewire\Admin\Results\Changes;
use App\Livewire\Admin\Results\EditResult;
use App\Livewire\Admin\Results\Notes;
use App\Livewire\Admin\Results\Publication;
use App\Livewire\Admin\Results\Reports;
use App\Livewire\Admin\Results\Signatures;
use App\Livewire\Student\Dashboard;
use App\Livewire\Student\StatusStatement;
use App\Models\Course;
use App\Models\Grade;
use App\Models\Registration;
use App\Models\RegistrationCourse;
use App\Models\ResultChange;
use App\Models\ResultPublication;
use App\Models\Setting;
use App\Models\Student;
use App\Models\StudentTermNote;
use App\Models\User;
use App\Models\Year;
use App\Services\GpaCalculator;
use App\Services\ResultReportService;
use App\Services\ResultUploadService;
use App\Services\StudentExcuseService;
use App\Services\TermNoteUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->world = billingWorld();
    seedResultScale();

    $this->student = $this->world['student'];
    $this->year = $this->world['year'];
    $this->course = $this->world['courses'][0];
    $this->enrollment = controlEnrollment($this->student, $this->year, Semester::FIRST, $this->course);

    $permissions = ['results.upload', 'results.edit', 'results.history', 'results.publish', 'results.notes', 'results.sheets', 'results.signatures', 'results.reports'];

    foreach ($permissions as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo($permissions);
});

function controlEnrollment(Student $student, Year $year, Semester $semester, Course $course, string $grade = 'Pending'): RegistrationCourse
{
    $registration = Registration::firstOrCreate(
        ['student_id' => $student->id, 'year_id' => $year->id, 'semester' => $semester->value],
        ['status' => RegistrationStatus::APPROVED],
    );

    return RegistrationCourse::create([
        'registration_id' => $registration->id,
        'course_id' => $course->id,
        'grade_id' => Grade::where('name', $grade)->value('id'),
    ]);
}

function controlStudent(array $world, string $code, string $name): Student
{
    return seatCardStudent($world, $name, $code);
}

describe('change log and manual edit', function () {
    it('logs an upload and skips re-uploading the same result', function () {
        $service = app(ResultUploadService::class);
        $row = ['registration_course_id' => $this->enrollment->id, 'written_score' => 40.0, 'coursework_score' => 45.0, 'total_score' => 85.0, 'exam_status' => null, 'grade_id' => Grade::where('name', 'B+')->value('id'), 'note' => null];

        $service->save([$row], $this->admin);
        $service->save([$row], $this->admin);

        $change = ResultChange::sole();

        expect($change->source)->toBe(ResultChangeSource::UPLOAD)
            ->and($change->before['grade'])->toBe('Pending')
            ->and($change->after['grade'])->toBe('B+')
            ->and($change->after['total_score'])->toEqual(85)
            ->and($change->user_id)->toBe($this->admin->id);
    });

    it('edits one result by the sheet rules and logs the reason', function () {
        $errors = app(ResultUploadService::class)->editResult($this->enrollment, '45', '46', '91', '', 'ممتاز', 'تظلم 15', $this->admin);

        $change = ResultChange::sole();

        expect($errors)->toBe([])
            ->and($this->enrollment->fresh()->grade->name)->toBe('A')
            ->and($this->enrollment->fresh()->result_note)->toBe('ممتاز')
            ->and($change->source)->toBe(ResultChangeSource::MANUAL)
            ->and($change->reason)->toBe('تظلم 15');
    });

    it('refuses a wrong edit and writes nothing', function (array $input, string $error) {
        $errors = app(ResultUploadService::class)->editResult($this->enrollment, ...$input, editedBy: $this->admin);

        expect($errors)->toContain($error)
            ->and($this->enrollment->fresh()->grade->name)->toBe('Pending')
            ->and(ResultChange::count())->toBe(0);
    })->with([
        'no reason' => [['45', '46', '91', '', null, ' '], 'اكتب سبب التعديل.'],
        'wrong total' => [['45', '46', '80', '', null, 'سبب'], 'المجموع 80 لا يساوي التحريري + أعمال السنة (91).'],
        'wrong grade' => [['45', '46', '91', 'C', null, 'سبب'], 'التقدير C خطأ، المجموع 91 تقديره A.'],
    ]);

    it('accepts an unscored grade such as W without marks', function () {
        expect(app(ResultUploadService::class)->editResult($this->enrollment, '', '', '', 'W', null, 'انسحاب', $this->admin))->toBe([])
            ->and($this->enrollment->fresh()->grade->name)->toBe('W');
    });

    it('logs the IC an excuse sets and the Pending its removal restores', function () {
        $excuse = app(StudentExcuseService::class)->add($this->student, $this->year, Semester::FIRST, ExcuseType::TERM, 'سفر', user: $this->admin);
        app(StudentExcuseService::class)->remove($excuse, $this->admin);

        $changes = ResultChange::query()->orderBy('id')->get();

        expect($changes)->toHaveCount(2)
            ->and($changes[0]->source)->toBe(ResultChangeSource::EXCUSE)
            ->and($changes[0]->after['grade'])->toBe('IC')
            ->and($changes[1]->after['grade'])->toBe('Pending');
    });

    it('edits a result from the screen and shows its history', function () {
        Livewire::actingAs($this->admin)
            ->test(EditResult::class)
            ->set('studentCode', $this->student->username)
            ->call('search')
            ->assertSee($this->course->name)
            ->call('edit', $this->enrollment->id)
            ->set('written', '30')
            ->set('coursework', '30')
            ->set('total', '60')
            ->set('reason', 'تصحيح')
            ->call('save')
            ->assertSet('resultErrors', [])
            ->assertDispatched('toast')
            ->call('edit', $this->enrollment->id)
            ->assertSee('تعديل يدوي')
            ->assertSee('تصحيح');

        expect($this->enrollment->fresh()->grade->name)->toBe('D+');
    });

    it('shows the problems of a wrong edit on the screen', function () {
        Livewire::actingAs($this->admin)
            ->test(EditResult::class)
            ->set('studentCode', $this->student->username)
            ->call('search')
            ->call('edit', $this->enrollment->id)
            ->set('written', '30')
            ->set('coursework', '30')
            ->set('total', '60')
            ->call('save')
            ->assertSee('اكتب سبب التعديل.');
    });

    it('filters the change log', function () {
        app(ResultUploadService::class)->editResult($this->enrollment, '', '', '', 'W', null, 'انسحاب', $this->admin);

        Livewire::actingAs($this->admin)
            ->test(Changes::class)
            ->assertSee('انسحاب')
            ->set('studentCode', 'NOPE')
            ->assertDontSee('انسحاب')
            ->set('studentCode', $this->student->username)
            ->set('source', ResultChangeSource::UPLOAD->value)
            ->assertDontSee('انسحاب');
    });
});

describe('publishing results', function () {
    beforeEach(function () {
        $this->enrollment->update(['grade_id' => Grade::where('name', 'A')->value('id')]);
    });

    it('hides the grades and GPA of an unpublished term from the student', function () {
        Livewire::actingAs($this->student, 'student')
            ->test(StatusStatement::class)
            ->assertSee('لم تُعلن بعد')
            ->assertDontSee('>A<', false);

        expect((new Dashboard)->calculateCumulativeStats($this->student->fresh())['cgpa'])->toBe(0.0);
    });

    it('shows them once the term is published', function () {
        Livewire::actingAs($this->admin)
            ->test(Publication::class)
            ->assertSee('غير معلنة')
            ->call('publish', $this->year->id, Semester::FIRST->value)
            ->assertSee('معلنة');

        Livewire::actingAs($this->student, 'student')
            ->test(StatusStatement::class)
            ->assertDontSee('لم تُعلن بعد');

        expect((new Dashboard)->calculateCumulativeStats($this->student->fresh())['cgpa'])->toBe(3.7);
    });

    it('hides them again after unpublishing', function () {
        ResultPublication::create(['year_id' => $this->year->id, 'semester' => Semester::FIRST->value]);

        Livewire::actingAs($this->admin)
            ->test(Publication::class)
            ->call('unpublish', $this->year->id, Semester::FIRST->value);

        expect(ResultPublication::isPublished($this->year->id, Semester::FIRST))->toBeFalse();
    });

    it('needs the publish permission', function () {
        Livewire::actingAs(User::factory()->create())
            ->test(Publication::class)
            ->call('publish', $this->year->id, Semester::FIRST->value)
            ->assertForbidden();
    });

    it('computes the GPA at the end of a term and over published terms only', function () {
        $second = $this->world['courses'][1];
        controlEnrollment($this->student, $this->year, Semester::SECOND, $second, 'F');
        $calculator = app(GpaCalculator::class);
        $student = $this->student->fresh();

        expect($calculator->cumulative($student, $this->year, Semester::FIRST)['gpa'])->toBe(3.7)
            ->and($calculator->cumulative($student)['gpa'])->toBe(1.85);

        ResultPublication::create(['year_id' => $this->year->id, 'semester' => Semester::FIRST->value]);

        expect($calculator->cumulative($student->fresh(), publishedOnly: true)['gpa'])->toBe(3.7);
    });
});

describe('term notes', function () {
    it('checks the whole sheet before writing anything', function () {
        $result = app(TermNoteUploadService::class)->analyze([
            ['كود الطالب', 'الملاحظة'],
            [$this->student->username, 'محروم من مادة'],
            ['NOPE', 'ملاحظة'],
            [$this->student->username, 'تاني'],
        ]);

        expect(collect($result['errors'])->pluck('message')->all())->toBe([
            'لا يوجد طالب بالكود NOPE.',
            "كود الطالب {$this->student->username} مكرر (ورد من قبل في السطر 2).",
        ]);
    });

    it('refuses a sheet without the note column', function () {
        expect(app(TermNoteUploadService::class)->analyze([['كود الطالب']])['errors'][0]['message'])->toBe('الملف ينقصه العمود "الملاحظة".');
    });

    it('saves the notes and clears a blank one', function () {
        $service = app(TermNoteUploadService::class);
        $other = controlStudent($this->world, 'CS250099', 'طالب تاني');
        StudentTermNote::create(['student_id' => $other->id, 'year_id' => $this->year->id, 'semester' => 'first', 'note' => 'قديمة']);

        $analysis = $service->analyze([['كود الطالب', 'ملاحظة'], [$this->student->username, 'غاب عن امتحان'], [$other->username, '']]);
        $service->save($analysis['rows'], $this->year, Semester::FIRST, $this->admin);

        expect(StudentTermNote::where('student_id', $this->student->id)->value('note'))->toBe('غاب عن امتحان')
            ->and(StudentTermNote::where('student_id', $other->id)->exists())->toBeFalse();
    });

    it('uploads notes from the screen', function () {
        $file = UploadedFile::fake()->createWithContent('notes.csv', "كود الطالب,الملاحظة\n{$this->student->username},ملاحظة الكنترول\n");

        Livewire::actingAs($this->admin)
            ->test(Notes::class)
            ->set('yearId', $this->year->id)
            ->set('semester', Semester::FIRST->value)
            ->set('sheet', $file)
            ->call('analyze')
            ->assertSee('ملاحظة الكنترول')
            ->call('confirm')
            ->assertSee('ملاحظات الترم (1)');

        expect(StudentTermNote::sole()->note)->toBe('ملاحظة الكنترول');
    });
});

it('saves the signature names and images', function () {
    Storage::fake('public');

    Livewire::actingAs($this->admin)
        ->test(Signatures::class)
        ->set('names.head', 'أ.د/ رئيس الكنترول')
        ->set('images.head', UploadedFile::fake()->image('head.png'))
        ->call('save')
        ->assertHasNoErrors();

    $settings = Setting::query()->first();

    expect($settings->signature_head_name)->toBe('أ.د/ رئيس الكنترول');
    Storage::disk('public')->assertExists($settings->signature_head_image);
});

describe('grade sheets', function () {
    beforeEach(function () {
        $this->enrollment->update(['grade_id' => Grade::where('name', 'B')->value('id'), 'written_score' => 40, 'coursework_score' => 41, 'total_score' => 81]);
        StudentTermNote::create(['student_id' => $this->student->id, 'year_id' => $this->year->id, 'semester' => 'first', 'note' => 'ملاحظة الترم الأول']);
        Setting::query()->firstOrCreate([])->update(['signature_dean_name' => 'عميد المعهد التجريبي']);
    });

    it('prints a page per student with marks, GPAs, notes and signatures', function () {
        $this->actingAs($this->admin)
            ->post(route('results.grade-sheets.print'), ['year_id' => $this->year->id])
            ->assertSuccessful()
            ->assertSee($this->student->name)
            ->assertSee($this->course->code)
            ->assertSee('81')
            ->assertSee('المعدل الفصلي: 3.00')
            ->assertSee('المعدل التراكمي: 3.00')
            ->assertSee('ملاحظة الترم الأول')
            ->assertSee('جيد جداً')
            ->assertSee('عميد المعهد التجريبي');
    });

    it('prints one term only, such as the summer sheet', function () {
        $this->actingAs($this->admin)
            ->post(route('results.grade-sheets.print'), ['year_id' => $this->year->id, 'semester' => Semester::SUMMER->value])
            ->assertSuccessful()
            ->assertSee('لا يوجد طلاب مطابقين للاختيار.');
    });

    it('filters by level and student code', function () {
        $other = controlStudent($this->world, 'CS250077', 'طالب في فرقة تانية');
        controlEnrollment($other, $this->year, Semester::FIRST, $this->world['courses'][1]);
        $other->update(['level_id' => \App\Models\Level::create(['name' => 'الفرقة الثانية'])->id]);

        $this->actingAs($this->admin)
            ->post(route('results.grade-sheets.print'), ['year_id' => $this->year->id, 'level_id' => $this->world['level']->id])
            ->assertSee($this->student->name)
            ->assertDontSee('طالب في فرقة تانية');

        $this->actingAs($this->admin)
            ->post(route('results.grade-sheets.print'), ['year_id' => $this->year->id, 'student_code' => 'CS250077'])
            ->assertSee('طالب في فرقة تانية')
            ->assertDontSee($this->student->name);
    });

    it('needs the sheets permission', function () {
        $this->actingAs(User::factory()->create())->get(route('results.grade-sheets.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('results.grade-sheets.index'))->assertSuccessful();
    });
});

describe('reports', function () {
    it('lists the courses still waiting for results', function () {
        $pending = app(ResultReportService::class)->pendingCourses($this->year, Semester::FIRST);

        expect($pending)->toHaveCount(1)
            ->and($pending[0]['course']->id)->toBe($this->course->id)
            ->and($pending[0]['pending'])->toBe(1);

        $this->enrollment->update(['grade_id' => Grade::where('name', 'A')->value('id')]);

        expect(app(ResultReportService::class)->pendingCourses($this->year, Semester::FIRST))->toBeEmpty();
    });

    it('ranks the students by cumulative GPA', function () {
        $this->enrollment->update(['grade_id' => Grade::where('name', 'C')->value('id')]);
        $top = controlStudent($this->world, 'CS250055', 'الأول على الدفعة');
        controlEnrollment($top, $this->year, Semester::FIRST, $this->course, 'A+');

        $ranking = app(ResultReportService::class)->ranking(null, null, 1);

        expect($ranking)->toHaveCount(1)
            ->and($ranking[0]['student']->id)->toBe($top->id)
            ->and($ranking[0]['classification'])->toBe('ممتاز');
    });

    it('counts each grade per course', function () {
        $this->enrollment->update(['grade_id' => Grade::where('name', 'A')->value('id')]);
        $other = controlStudent($this->world, 'CS250044', 'طالب');
        controlEnrollment($other, $this->year, Semester::FIRST, $this->course, 'A');

        $distribution = app(ResultReportService::class)->gradeDistribution($this->year, Semester::FIRST);

        expect($distribution['courses'][0]['total'])->toBe(2)
            ->and($distribution['courses'][0]['counts'][Grade::where('name', 'A')->value('id')])->toBe(2);
    });

    it('renders every report', function () {
        Livewire::actingAs($this->admin)
            ->test(Reports::class)
            ->assertSee($this->course->name)
            ->set('report', 'ranking')
            ->assertSee('لا يوجد طلاب لهم معدل.')
            ->set('report', 'distribution')
            ->assertSee('A+');
    });
});
