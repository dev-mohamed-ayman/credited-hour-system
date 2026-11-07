<?php

use App\Enums\RegistrationStatus;
use App\Enums\Semester;
use App\Enums\Student\StudentStatus;
use App\Enums\Student\StudyStatus;
use App\Livewire\Admin\Results\EndOfTerm;
use App\Livewire\Admin\Student\Show;
use App\Models\Course;
use App\Models\Grade;
use App\Models\Level;
use App\Models\Registration;
use App\Models\RegistrationCourse;
use App\Models\Setting;
use App\Models\TermClosure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->world = billingWorld();
    seedResultScale();

    $this->world['level']->update(['promotion_hours' => 27]);
    $this->secondLevel = Level::create(['name' => 'الفرقة الثانية']);
    Setting::query()->firstOrCreate([])->update(['warning_gpa_threshold' => 2.0, 'graduation_required_hours' => 132]);

    $this->student = $this->world['student'];
    $this->student->update(['status' => StudentStatus::REGISTERED->value, 'study_status' => StudyStatus::FRESHMAN->value]);

    $registration = Registration::create([
        'student_id' => $this->student->id, 'year_id' => $this->world['year']->id,
        'semester' => Semester::FIRST->value, 'status' => RegistrationStatus::APPROVED,
    ]);
    $course = Course::create([
        'code' => 'BIG27', 'name' => 'مادة', 'hours' => 27, 'is_selected' => false, 'is_active' => true,
        'department_id' => $this->world['department']->id, 'level_id' => $this->world['level']->id, 'semester' => 'الأول',
    ]);
    RegistrationCourse::create(['registration_id' => $registration->id, 'course_id' => $course->id, 'grade_id' => Grade::where('name', 'B')->value('id')]);

    foreach (['term_closures.view', 'term_closures.execute', 'term_closures.revert', 'students.view'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
});

function endOfTermUser(array $permissions): User
{
    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

function summerScreen(User $user): \Livewire\Features\SupportTesting\Testable
{
    return Livewire::actingAs($user)
        ->test(EndOfTerm::class)
        ->set('yearId', test()->world['year']->id)
        ->set('semester', Semester::SUMMER->value);
}

it('needs the view permission', function () {
    $this->actingAs(endOfTermUser([]))->get(route('results.term-closure'))->assertForbidden();
    $this->actingAs(endOfTermUser(['term_closures.view']))->get(route('results.term-closure'))->assertSuccessful();
});

it('simulates the summer without saving anything', function () {
    summerScreen(endOfTermUser(['term_closures.view']))
        ->call('simulate')
        ->assertSee('محاكاة (لم يتم حفظ أي شيء)')
        ->assertSee($this->student->username)
        ->assertSee('ترقية: 1')
        ->assertSee('الفرقة الثانية');

    expect(TermClosure::count())->toBe(0)
        ->and($this->student->fresh()->level_id)->toBe($this->world['level']->id);
});

it('runs the closure once and shows its records', function () {
    summerScreen(endOfTermUser(['term_closures.view', 'term_closures.execute']))
        ->call('execute')
        ->assertDispatched('toast')
        ->assertSee('نتيجة تنفيذ الترم الصيفي 2025-2026')
        ->assertSee('تم تنفيذ إجراءات نهاية هذا الترم من قبل.');

    expect($this->student->fresh()->level_id)->toBe($this->secondLevel->id);
});

it('does not let a viewer execute or revert', function () {
    summerScreen(endOfTermUser(['term_closures.view']))->call('execute')->assertForbidden();

    $closure = TermClosure::factory()->create(['year_id' => $this->world['year']->id, 'semester' => Semester::SUMMER]);

    summerScreen(endOfTermUser(['term_closures.view']))->call('revert', $closure->id)->assertForbidden();
});

it('shows the blockers and reports them on execute', function () {
    $this->world['level']->update(['promotion_hours' => null]);
    Level::create(['name' => 'الفرقة الثالثة']);

    summerScreen(endOfTermUser(['term_closures.view', 'term_closures.execute']))
        ->assertSee('لم يتم تحديد ساعات الترقية للفرق')
        ->call('execute')
        ->assertDispatched('toast', fn (string $name, array $params) => $params[0]['type'] === 'error');

    expect(TermClosure::count())->toBe(0);
});

it('reverts the latest closure from the history', function () {
    $user = endOfTermUser(['term_closures.view', 'term_closures.execute', 'term_closures.revert']);
    summerScreen($user)->call('execute');

    summerScreen($user)
        ->call('revert', TermClosure::firstOrFail()->id)
        ->assertSee('لم يتم تنفيذ إجراءات أي ترم بعد.');

    expect($this->student->fresh()->level_id)->toBe($this->world['level']->id);
});

it("lists the student's term history on the student page", function () {
    app(\App\Services\TermClosureService::class)->close($this->world['year'], Semester::SUMMER);

    Livewire::actingAs(endOfTermUser(['students.view']))
        ->test(Show::class, ['student' => $this->student])
        ->assertSee('سجل الترمات')
        ->assertSee('الترم الصيفي 2025-2026')
        ->assertSee('ترقية');
});

it('saves promotion hours on a level', function () {
    foreach (['levels.view', 'levels.edit'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    $this->actingAs(endOfTermUser(['levels.view', 'levels.edit']))
        ->put(route('levels.update', $this->secondLevel), ['name' => 'الفرقة الثانية', 'promotion_hours' => 60])
        ->assertRedirect(route('levels.index'));

    expect($this->secondLevel->fresh()->promotion_hours)->toBe(60);
});
