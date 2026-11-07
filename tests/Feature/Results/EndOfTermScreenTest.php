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

    $this->secondLevel = Level::create(['name' => 'الفرقة الثانية', 'start_hours' => 27]);
    Setting::query()->firstOrCreate([])->update(['warning_gpa_threshold' => 2.0, 'graduation_required_hours' => 132]);

    $this->student = $this->world['student'];
    $this->student->update(['status' => StudentStatus::REGISTERED->value, 'study_status' => StudyStatus::FRESHMAN->value]);

    $registration = Registration::create([
        'student_id' => $this->student->id, 'year_id' => \App\Models\Year::create(['year' => '2024-2025'])->id,
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
    $this->secondLevel->update(['start_hours' => null]);

    summerScreen(endOfTermUser(['term_closures.view', 'term_closures.execute']))
        ->assertSee('لم يتم تحديد ساعات بداية الفرق')
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

it('links every simulated student to their page to review the grades', function () {
    summerScreen(endOfTermUser(['term_closures.view', 'students.view']))
        ->call('simulate')
        ->assertSee(route('students.show', $this->student->id), false);
});

describe('promotion and warning settings', function () {
    beforeEach(function () {
        Permission::firstOrCreate(['name' => 'term_closures.settings', 'guard_name' => 'web']);
        $this->thirdLevel = Level::create(['name' => 'الفرقة الثالثة', 'start_hours' => 60]);
        $this->settingsUser = endOfTermUser(['term_closures.view', 'term_closures.settings']);
    });

    it('shows the stored rules', function () {
        Livewire::actingAs($this->settingsUser)
            ->test(EndOfTerm::class)
            ->assertSet("levelStartHours.{$this->secondLevel->id}", 27)
            ->assertSet('graduationHours', 132)
            ->assertSet('exemptHours', 105)
            ->assertSet('dismissalWarnings', 3)
            ->assertSet('maxYearsInLevel', 5);
    });

    it('saves the start hours of every level and the warning rules', function () {
        Livewire::actingAs($this->settingsUser)
            ->test(EndOfTerm::class)
            ->set("levelStartHours.{$this->secondLevel->id}", 30)
            ->set("levelStartHours.{$this->thirdLevel->id}", 64)
            ->set('graduationHours', 140)
            ->set('warningThreshold', 1.8)
            ->set('exemptHours', 100)
            ->set('dismissalWarnings', 4)
            ->set('maxYearsInLevel', 6)
            ->call('saveRules')
            ->assertHasNoErrors();

        $settings = Setting::query()->first();

        expect($this->secondLevel->fresh()->start_hours)->toBe(30)
            ->and($this->thirdLevel->fresh()->start_hours)->toBe(64)
            ->and($this->world['level']->fresh()->start_hours)->toBeNull()
            ->and($settings->graduation_required_hours)->toBe(140)
            ->and((float) $settings->warning_gpa_threshold)->toBe(1.8)
            ->and($settings->warning_exempt_earned_hours)->toBe(100)
            ->and($settings->dismissal_warnings)->toBe(4)
            ->and($settings->max_years_in_level)->toBe(6);
    });

    it('needs every level to start after the one before it, and graduation after the last', function (string $field, int $hours, string $error) {
        $fields = [
            'second' => "levelStartHours.{$this->secondLevel->id}",
            'third' => "levelStartHours.{$this->thirdLevel->id}",
            'graduation' => 'graduationHours',
        ];

        Livewire::actingAs($this->settingsUser)
            ->test(EndOfTerm::class)
            ->set($fields[$field], $hours)
            ->call('saveRules')
            ->assertHasErrors([$fields[$field]])
            ->assertSee($error);

        expect($this->thirdLevel->fresh()->start_hours)->toBe(60);
    })->with([
        'third not after second' => ['third', 20, 'لازم تكون أكبر من ساعات بداية الفرقة اللي قبلها.'],
        'graduation not after the last level' => ['graduation', 60, 'ساعات التخرج لازم تكون أكبر من ساعات بداية آخر فرقة.'],
        'missing start hours' => ['second', 0, 'لازم تكون ساعة واحدة على الأقل.'],
    ]);

    it('needs the settings permission', function () {
        Livewire::actingAs(endOfTermUser(['term_closures.view']))
            ->test(EndOfTerm::class)
            ->call('saveRules')
            ->assertForbidden();
    });
});
