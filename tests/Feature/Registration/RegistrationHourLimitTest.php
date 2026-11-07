<?php

use App\Enums\Semester;
use App\Livewire\Admin\GradeSettings\Index as GradeSettings;
use App\Livewire\Student\CourseRegistration\Index as StudentRegistrationIndex;
use App\Models\RegistrationCourse;
use App\Models\RegistrationHourLimit;
use App\Models\Setting;
use App\Models\TermClosure;
use App\Models\User;
use App\Services\CourseRegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->world = billingWorld();
    $this->student = $this->world['student'];
    $this->courses = $this->world['courses'];
});

function hourLimit(array $world, int $maxHours, Semester $semester = Semester::FIRST): void
{
    RegistrationHourLimit::create([
        'department_id' => $world['department']->id,
        'level_id' => $world['level']->id,
        'semester' => $semester->value,
        'max_hours' => $maxHours,
    ]);
}

function registerAsStudent(array $world, array $courseIds): array
{
    test()->actingAs($world['student'], 'student');

    return app(CourseRegistrationService::class)->save($world['student'], $world['year'], Semester::FIRST, $courseIds);
}

it('registers freely when no limit is set', function () {
    $result = registerAsStudent($this->world, $this->courses->pluck('id')->all());

    expect($result['success'])->toBeTrue()
        ->and(RegistrationCourse::count())->toBe(2);
});

it('rejects the courses that go past the department and level limit', function () {
    hourLimit($this->world, 3);

    $result = registerAsStudent($this->world, $this->courses->pluck('id')->all());

    expect($result['success'])->toBeTrue()
        ->and($result['rejected_course_ids'])->toBe([$this->courses[1]->id])
        ->and($result['message'])->toContain('تم تجاوز الحد الأقصى للساعات في الترم (3 ساعة)')
        ->and(RegistrationCourse::count())->toBe(1);
});

it('counts the hours already registered in the term', function () {
    hourLimit($this->world, 5);

    registerAsStudent($this->world, [$this->courses[0]->id]);
    $result = registerAsStudent($this->world, [$this->courses[1]->id]);

    expect($result['success'])->toBeFalse()
        ->and(RegistrationCourse::count())->toBe(1);
});

it('keeps required courses before optional ones within the limit', function () {
    hourLimit($this->world, 3);
    $this->courses[0]->update(['is_selected' => true]);

    $result = registerAsStudent($this->world, [$this->courses[0]->id, $this->courses[1]->id]);

    expect($result['rejected_course_ids'])->toBe([$this->courses[0]->id])
        ->and(RegistrationCourse::query()->value('course_id'))->toBe($this->courses[1]->id);
});

it('only applies a limit to its own department, level and term', function () {
    hourLimit($this->world, 3, Semester::SECOND);

    expect(app(CourseRegistrationService::class)->getMaxHours($this->student, Semester::FIRST))->toBeNull()
        ->and(app(CourseRegistrationService::class)->getMaxHours($this->student, Semester::SECOND))->toBe(3);
});

it('caps a student with an active academic warning at the warned limit', function () {
    hourLimit($this->world, 18);
    Setting::query()->firstOrCreate([])->update(['warned_max_hours' => 12]);
    $service = app(CourseRegistrationService::class);

    expect($service->getMaxHours($this->student, Semester::FIRST))->toBe(18);

    $closure = TermClosure::factory()->create(['year_id' => $this->world['year']->id]);
    $this->student->warnings()->create(['term_closure_id' => $closure->id, 'type' => 'warning', 'reason' => 'إنذار', 'is_active' => true]);

    expect($service->getMaxHours($this->student, Semester::FIRST))->toBe(12);

    $this->student->warnings()->update(['is_active' => false]);
    $this->student->warnings()->create(['type' => 'warning', 'reason' => 'تنبيه يدوي', 'is_active' => true]);

    expect($service->getMaxHours($this->student, Semester::FIRST))->toBe(18);
});

it('shows the term hours against the limit on the registration screen', function () {
    hourLimit($this->world, 6);

    Livewire::actingAs($this->student, 'student')
        ->test(StudentRegistrationIndex::class)
        ->set('selectedDue', [$this->courses[0]->id])
        ->assertSet('maxHours', 6)
        ->assertSee('ساعات الترم:')
        ->assertSee('3 / 6');
});

describe('settings', function () {
    beforeEach(function () {
        foreach (['course_registration_settings.view', 'course_registration_settings.edit'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo(['course_registration_settings.view', 'course_registration_settings.edit']);
    });

    it('saves and clears the limits of the chosen department', function () {
        Livewire::actingAs($this->admin)
            ->test(GradeSettings::class)
            ->set('hourLimitDepartmentId', $this->world['department']->id)
            ->set("hourLimits.{$this->world['level']->id}.first", 18)
            ->set('warnedMaxHours', 12)
            ->call('saveHourLimits')
            ->assertHasNoErrors();

        expect(RegistrationHourLimit::where('level_id', $this->world['level']->id)->value('max_hours'))->toBe(18)
            ->and(Setting::warnedMaxHours())->toBe(12);

        Livewire::actingAs($this->admin)
            ->test(GradeSettings::class)
            ->assertSet("hourLimits.{$this->world['level']->id}.first", 18)
            ->set("hourLimits.{$this->world['level']->id}.first", '')
            ->set('warnedMaxHours', '')
            ->call('saveHourLimits');

        expect(RegistrationHourLimit::count())->toBe(0)
            ->and(Setting::warnedMaxHours())->toBeNull();
    });

    it('rejects a limit that is not a positive whole number', function () {
        Livewire::actingAs($this->admin)
            ->test(GradeSettings::class)
            ->set("hourLimits.{$this->world['level']->id}.first", 0)
            ->call('saveHourLimits')
            ->assertHasErrors(["hourLimits.{$this->world['level']->id}.first" => 'min']);
    });
});
