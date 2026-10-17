<?php

use App\Enums\ExamResultStatus;
use App\Enums\RegistrationStatus;
use App\Enums\Semester;
use App\Enums\SemesterStatus;
use App\Livewire\Admin\Results\Upload;
use App\Models\ExamCommittee;
use App\Models\ExamCommitteeStudent;
use App\Models\Grade;
use App\Models\Registration;
use App\Models\RegistrationCourse;
use App\Models\Student;
use App\Models\User;
use App\Services\ResultUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

const RESULT_HEADER = ['كود الطالب', 'رقم الجلوس', 'التحريري', 'اعمال السنة', 'المجموع', 'التقدير', 'ملاحظة'];

beforeEach(function () {
    $this->world = billingWorld();
    $this->world['year']->update(['first_semester_status' => SemesterStatus::CLOSED_REGISTRATION]);
    seedResultScale();

    Permission::firstOrCreate(['name' => 'results.upload', 'guard_name' => 'web']);
    $this->world['admin']->givePermissionTo('results.upload');

    $this->course = $this->world['courses'][0];
    $this->first = $this->world['student'];
    $this->second = resultStudent($this->world, 'CS250002');

    $this->firstEnrollment = enrollForResult($this->first, $this->world, $this->course->id);
    $this->secondEnrollment = enrollForResult($this->second, $this->world, $this->course->id);
});

function seedResultScale(): void
{
    $scale = [
        ['A+', 4.00, 95, 100, true], ['A', 3.70, 90, 94, true], ['B+', 3.40, 85, 89, true], ['B', 3.00, 80, 84, true],
        ['C+', 2.70, 72, 79, true], ['C', 2.30, 65, 71, true], ['D+', 2.20, 55, 64, true], ['D', 2.00, 50, 54, true],
        ['F', 0.00, 0, 49, true], ['FX', 0.00, null, null, true], ['IC', null, null, null, false], ['W', null, null, null, false],
    ];

    foreach ($scale as $order => [$name, $points, $min, $max, $countsInGpa]) {
        Grade::updateOrCreate(['name' => $name], [
            'points' => $points, 'min_score' => $min, 'max_score' => $max,
            'counts_in_gpa' => $countsInGpa, 'order' => $order + 1, 'is_pending_default' => false,
        ]);
    }

    Grade::pendingDefault()->update(['counts_in_gpa' => false]);
}

function resultStudent(array $world, string $code): Student
{
    $student = $world['student']->replicate();
    $student->forceFill(['username' => $code, 'national_id' => '2990101'.substr($code, -7), 'name' => 'طالب '.$code])->save();

    return $student;
}

function enrollForResult(Student $student, array $world, int $courseId, RegistrationStatus $status = RegistrationStatus::APPROVED): RegistrationCourse
{
    $registration = Registration::firstOrCreate(
        ['student_id' => $student->id, 'year_id' => $world['year']->id, 'semester' => Semester::FIRST->value],
        ['status' => $status],
    );

    return RegistrationCourse::create([
        'registration_id' => $registration->id,
        'course_id' => $courseId,
        'grade_id' => Grade::pendingDefault()->id,
    ]);
}

function giveSeat(Student $student, array $world, int $seatNumber): void
{
    $committee = ExamCommittee::query()->where('year_id', $world['year']->id)->first()
        ?? ExamCommittee::factory()->create(['year_id' => $world['year']->id, 'semester' => Semester::FIRST]);

    ExamCommitteeStudent::create(['exam_committee_id' => $committee->id, 'student_id' => $student->id, 'seat_number' => $seatNumber]);
}

/**
 * @param  array<int, array<int, mixed>>  $rows
 */
function resultSheet(array $rows, array $header = RESULT_HEADER): UploadedFile
{
    $path = sys_get_temp_dir().'/'.uniqid('result-test-').'.xlsx';
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->addRow(Row::fromValues($header));

    foreach ($rows as $row) {
        $writer->addRow(Row::fromValues($row));
    }

    $writer->close();

    return UploadedFile::fake()->createWithContent('result.xlsx', file_get_contents($path));
}

/**
 * @param  array<int, array<int, mixed>>  $rows
 * @return array<string, mixed>
 */
function analyzeResultRows(array $rows, array $world, $course): array
{
    return app(ResultUploadService::class)->analyze(
        [RESULT_HEADER, ...$rows],
        $course,
        $world['year'],
        Semester::FIRST,
    );
}

function gradeId(string $name): int
{
    return Grade::query()->where('name', $name)->value('id');
}

it('accepts a clean sheet and resolves each grade from the total', function () {
    $analysis = analyzeResultRows([
        ['CS250001', null, 45, 47, 92, 'A', 'ممتاز'],
        ['CS250002', null, 20, 25.5, 45.5, null, null],
    ], $this->world, $this->course);

    expect($analysis['errors'])->toBe([])
        ->and($analysis['rows'])->toHaveCount(2)
        ->and($analysis['rows'][0]['grade_name'])->toBe('A')
        ->and($analysis['rows'][1]['grade_name'])->toBe('F')
        ->and($analysis['rows'][1]['total_score'])->toBe(45.5)
        ->and($analysis['missing'])->toBe([]);
});

it('maps every boundary of the scale to the right grade', function (float $written, float $coursework, string $grade) {
    $analysis = analyzeResultRows([
        ['CS250001', null, $written, $coursework, $written + $coursework, $grade, null],
    ], $this->world, $this->course);

    expect($analysis['errors'])->toBe([])
        ->and($analysis['rows'][0]['grade_name'])->toBe($grade);
})->with([
    '100 → A+' => [50, 50, 'A+'],
    '95 → A+' => [50, 45, 'A+'],
    '94.5 → A' => [50, 44.5, 'A'],
    '90 → A' => [45, 45, 'A'],
    '85 → B+' => [40, 45, 'B+'],
    '80 → B' => [40, 40, 'B'],
    '72 → C+' => [36, 36, 'C+'],
    '65 → C' => [30, 35, 'C'],
    '55 → D+' => [30, 25, 'D+'],
    '50 → D' => [25, 25, 'D'],
    '49.99 → F' => [24.99, 25, 'F'],
    '0 → F' => [0, 0, 'F'],
]);

it('rejects rows that break the result rules', function (array $row, string $expectedMessage) {
    $analysis = analyzeResultRows([$row], $this->world, $this->course);

    expect($analysis['rows'])->toBe([])
        ->and(collect($analysis['errors'])->pluck('message')->implode(' | '))->toContain($expectedMessage)
        ->and($analysis['errors'][0]['row'])->toBe(2);
})->with([
    'wrong grade for the total' => [['CS250001', null, 45, 47, 92, 'B', null], 'التقدير B خطأ'],
    'total is not the sum' => [['CS250001', null, 45, 40, 90, 'A', null], 'لا يساوي التحريري + أعمال السنة'],
    'written above 50' => [['CS250001', null, 51, 40, 91, 'A', null], 'يجب أن تكون بين 0 و 50'],
    'negative coursework' => [['CS250001', null, 40, -1, 39, 'F', null], 'يجب أن تكون بين 0 و 50'],
    'missing coursework' => [['CS250001', null, 40, null, 40, 'F', null], 'فارغة'],
    'unknown grade' => [['CS250001', null, 45, 47, 92, 'Z', null], 'غير موجود في جدول التقديرات'],
    'pending grade' => [['CS250001', null, null, null, null, 'Pending', null], 'لا يمكن رفع التقدير'],
    'scored grade without marks' => [['CS250001', null, null, null, null, 'B', null], 'يحتاج إلى درجات'],
    'no marks and no grade' => [['CS250001', null, null, null, null, null, 'ملاحظة فقط'], 'لا توجد درجات ولا تقدير'],
    'free text mark' => [['CS250001', null, 'ممتاز', 40, 90, 'A', null], 'غير صحيحة'],
    'absent total with numeric written' => [['CS250001', null, 30, 20, 'غائب', 'F', null], 'يتطلب أن يكون التحريري'],
    'absent with a passing grade' => [['CS250001', null, 'غائب', 20, 'غائب', 'C', null], 'يعني رسوب'],
    'numeric total with absent written' => [['CS250001', null, 'غائب', 20, 20, 'F', null], 'ليس رقماً'],
    'cancelled in the written column' => [['CS250001', null, 'الغاء', 20, 'الغاء', 'F', null], 'غير صحيحة'],
    'student not in the course' => [['CS999999', null, 45, 47, 92, 'A', null], 'غير مسجل في هذه المادة'],
    'empty student code' => [[null, null, 45, 47, 92, 'A', null], 'كود الطالب فارغ'],
]);

it('accepts absences, disciplinary cases, cancellations and unscored grades as F/IC/W/FX', function () {
    $third = resultStudent($this->world, 'CS250003');
    $fourth = resultStudent($this->world, 'CS250004');
    $fifth = resultStudent($this->world, 'CS250005');
    foreach ([$third, $fourth, $fifth] as $student) {
        enrollForResult($student, $this->world, $this->course->id);
    }

    $analysis = analyzeResultRows([
        ['CS250001', null, 'غائب', 30, 'غائب', 'F', null],
        ['CS250002', null, 'تأديب', 'تأديب', 'تأديب', null, null],
        ['CS250003', null, 40, 30, 'إلغاء', 'F', null],
        ['CS250004', null, null, null, null, 'IC', null],
        ['CS250005', null, null, null, null, 'w', null],
    ], $this->world, $this->course);

    expect($analysis['errors'])->toBe([]);

    $rows = collect($analysis['rows'])->keyBy('code');

    expect($rows['CS250001'])->toMatchArray(['grade_name' => 'F', 'exam_status' => ExamResultStatus::ABSENT->value, 'written_score' => null, 'coursework_score' => 30.0])
        ->and($rows['CS250002'])->toMatchArray(['grade_name' => 'F', 'exam_status' => ExamResultStatus::DISCIPLINARY->value])
        ->and($rows['CS250003'])->toMatchArray(['grade_name' => 'F', 'exam_status' => ExamResultStatus::CANCELLED->value, 'total_score' => null])
        ->and($rows['CS250004']['grade_name'])->toBe('IC')
        ->and($rows['CS250005']['grade_name'])->toBe('W');
});

it('flags duplicated codes, pending registrations and students missing from the sheet', function () {
    $pendingStudent = resultStudent($this->world, 'CS250003');
    enrollForResult($pendingStudent, $this->world, $this->course->id, RegistrationStatus::PENDING);

    $analysis = analyzeResultRows([
        ['CS250001', null, 45, 47, 92, 'A', null],
        ['cs250001', null, 40, 40, 80, 'B', null],
        ['CS250003', null, 40, 40, 80, 'B', null],
    ], $this->world, $this->course);

    $messages = collect($analysis['errors'])->pluck('message', 'row');

    expect($messages[3])->toContain('مكرر')
        ->and($messages[4])->toContain('لم يتم اعتماده')
        ->and($analysis['missing'])->toBe([['code' => 'CS250002', 'name' => 'طالب CS250002']]);
});

it('checks the seat number against the student exam committee seat', function () {
    giveSeat($this->first, $this->world, 101);

    $wrongSeat = analyzeResultRows([['CS250001', 102, 45, 47, 92, 'A', null]], $this->world, $this->course);
    $emptySeat = analyzeResultRows([['CS250001', null, 45, 47, 92, 'A', null]], $this->world, $this->course);
    $noSeatAssigned = analyzeResultRows([['CS250002', 500, 45, 47, 92, 'A', null]], $this->world, $this->course);
    $rightSeat = analyzeResultRows([['CS250001', '101', 45, 47, 92, 'A', null]], $this->world, $this->course);

    expect($wrongSeat['errors'][0]['message'])->toContain('غير مطابق')
        ->and($emptySeat['errors'][0]['message'])->toContain('رقم الجلوس فارغ')
        ->and($noSeatAssigned['errors'][0]['message'])->toContain('لا يوجد رقم جلوس')
        ->and($rightSeat['errors'])->toBe([]);
});

it('rejects a sheet whose header does not match', function () {
    $analysis = app(ResultUploadService::class)->analyze(
        [['كود الطالب', 'التحريري', 'رقم الجلوس', 'اعمال السنة', 'المجموع', 'التقدير', 'ملاحظة'], ['CS250001', 45, null, 47, 92, 'A', null]],
        $this->course,
        $this->world['year'],
        Semester::FIRST,
    );

    expect($analysis['rows'])->toBe([])
        ->and($analysis['errors'][0]['message'])->toContain('العمود رقم 2');
});

it('accepts header spelling variants and arabic digits', function () {
    $analysis = app(ResultUploadService::class)->analyze(
        [['كود الطالب ', 'رقم الجلوس', 'التحريرى', 'أعمال السنه', 'المجموع', 'التقدير', 'ملاحظه'], ['CS250001', null, '٤٥', '٤٧', '٩٢', 'a', null]],
        $this->course,
        $this->world['year'],
        Semester::FIRST,
    );

    expect($analysis['errors'])->toBe([])
        ->and($analysis['rows'][0]['grade_name'])->toBe('A');
});

it('previews then saves the results after confirmation', function () {
    giveSeat($this->first, $this->world, 101);
    giveSeat($this->second, $this->world, 102);

    $component = Livewire::actingAs($this->world['admin'])
        ->test(Upload::class)
        ->set('yearId', $this->world['year']->id)
        ->set('semester', Semester::FIRST->value)
        ->set('courseId', $this->course->id)
        ->set('sheet', resultSheet([
            ['CS250001', 101, 45, 47, 92, 'A', 'ممتاز'],
            ['CS250002', 102, 'غائب', 20, 'غائب', 'F', null],
        ]))
        ->call('analyze')
        ->assertHasNoErrors()
        ->assertSee('الملف سليم: 2 طالب');

    expect($this->firstEnrollment->fresh()->grade->is_pending_default)->toBeTrue();

    $component->call('confirm')->assertSet('savedCount', 2)->assertSet('analysis', null);

    $first = $this->firstEnrollment->fresh();
    $second = $this->secondEnrollment->fresh();

    expect($first->grade_id)->toBe(gradeId('A'))
        ->and((float) $first->written_score)->toBe(45.0)
        ->and((float) $first->coursework_score)->toBe(47.0)
        ->and((float) $first->total_score)->toBe(92.0)
        ->and($first->result_note)->toBe('ممتاز')
        ->and($first->result_uploaded_by)->toBe($this->world['admin']->id)
        ->and($first->result_uploaded_at)->not->toBeNull()
        ->and($second->grade_id)->toBe(gradeId('F'))
        ->and($second->exam_status)->toBe(ExamResultStatus::ABSENT)
        ->and($second->written_score)->toBeNull()
        ->and((float) $second->coursework_score)->toBe(20.0);
});

it('saves nothing when a single row is wrong', function () {
    Livewire::actingAs($this->world['admin'])
        ->test(Upload::class)
        ->set('yearId', $this->world['year']->id)
        ->set('semester', Semester::FIRST->value)
        ->set('courseId', $this->course->id)
        ->set('sheet', resultSheet([
            ['CS250001', null, 45, 47, 92, 'A', null],
            ['CS250002', null, 45, 47, 92, 'C', null],
        ]))
        ->call('confirm')
        ->assertSet('savedCount', null)
        ->assertSee('يوجد أخطاء في الملف ولن يتم الحفظ');

    expect($this->firstEnrollment->fresh()->grade->is_pending_default)->toBeTrue()
        ->and($this->secondEnrollment->fresh()->grade->is_pending_default)->toBeTrue();
});

it('reports how many existing results a re-upload will overwrite', function () {
    $this->firstEnrollment->update(['grade_id' => gradeId('B')]);

    Livewire::actingAs($this->world['admin'])
        ->test(Upload::class)
        ->set('yearId', $this->world['year']->id)
        ->set('semester', Semester::FIRST->value)
        ->set('courseId', $this->course->id)
        ->set('sheet', resultSheet([['CS250001', null, 45, 47, 92, 'A', null]]))
        ->call('analyze')
        ->assertSee('سيتم تعديل نتيجة 1 طالب');
});

it('refuses uploads while the term registration is open', function () {
    $this->world['year']->update(['first_semester_status' => SemesterStatus::OPEN_REGISTRATION]);

    Livewire::actingAs($this->world['admin'])
        ->test(Upload::class)
        ->set('yearId', $this->world['year']->id)
        ->set('semester', Semester::FIRST->value)
        ->set('courseId', $this->course->id)
        ->set('sheet', resultSheet([['CS250001', null, 45, 47, 92, 'A', null]]))
        ->call('confirm')
        ->assertHasErrors('semester');

    expect($this->firstEnrollment->fresh()->grade->is_pending_default)->toBeTrue();
});

it('rejects files that are not xlsx or csv', function () {
    Livewire::actingAs($this->world['admin'])
        ->test(Upload::class)
        ->set('yearId', $this->world['year']->id)
        ->set('semester', Semester::FIRST->value)
        ->set('courseId', $this->course->id)
        ->set('sheet', UploadedFile::fake()->create('result.pdf', 10))
        ->call('analyze')
        ->assertHasErrors('sheet');
});

it('reads csv sheets saved from excel with a byte order mark', function () {
    $csv = "\xEF\xBB\xBF".implode(',', RESULT_HEADER)."\nCS250001,,45,47,92,A,\n";

    Livewire::actingAs($this->world['admin'])
        ->test(Upload::class)
        ->set('yearId', $this->world['year']->id)
        ->set('semester', Semester::FIRST->value)
        ->set('courseId', $this->course->id)
        ->set('sheet', UploadedFile::fake()->createWithContent('result.csv', $csv))
        ->call('confirm')
        ->assertSet('savedCount', 1);

    expect($this->firstEnrollment->fresh()->grade_id)->toBe(gradeId('A'));
});

it('downloads a template listing the approved students with their seats', function () {
    giveSeat($this->first, $this->world, 101);

    Livewire::actingAs($this->world['admin'])
        ->test(Upload::class)
        ->set('yearId', $this->world['year']->id)
        ->set('semester', Semester::FIRST->value)
        ->set('courseId', $this->course->id)
        ->call('downloadTemplate')
        ->assertFileDownloaded('نتيجة CS101.xlsx');
});

it('is reachable only with the upload permission', function () {
    $this->actingAs($this->world['admin'])
        ->get(route('results.upload'))
        ->assertSuccessful();

    $this->actingAs(User::factory()->create())
        ->get(route('results.upload'))
        ->assertForbidden();
});
