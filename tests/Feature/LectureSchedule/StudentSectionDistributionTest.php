<?php

use App\Enums\Student\StudentStatus;
use App\Livewire\Admin\RegistrationFee\Index;
use App\Models\Level;
use App\Models\RegistrationFee;
use App\Models\Section;
use App\Models\Student;
use App\Services\StudentSectionDistributionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function distributionService(): StudentSectionDistributionService
{
    return app(StudentSectionDistributionService::class);
}

function configureStudentsPerSection(array $world, int $perSection): void
{
    RegistrationFee::updateOrCreate(
        ['department_id' => $world['department']->id, 'level_id' => $world['level']->id],
        ['number_of_students_per_section' => $perSection],
    );
}

test('undistributed students are split into numbered sections by the configured size', function () {
    $world = schedulingWorld(0);
    configureStudentsPerSection($world, 4);
    seedSectionStudents(null, 10, $world);

    $assigned = distributionService()->distribute($world['department']->id, $world['level']->id);

    expect($assigned)->toBe(10)
        ->and(distributionService()->studentsCountPerSection($world['department']->id, $world['level']->id))
        ->toBe([1 => 4, 2 => 4, 3 => 2]);
});

test('distribution fills free seats in existing sections before opening new ones', function () {
    $world = schedulingWorld(0);
    configureStudentsPerSection($world, 3);
    seedSectionStudents(1, 3, $world);
    seedSectionStudents(2, 1, $world);
    seedSectionStudents(null, 4, $world);

    distributionService()->distribute($world['department']->id, $world['level']->id);

    expect(distributionService()->studentsCountPerSection($world['department']->id, $world['level']->id))
        ->toBe([1 => 3, 2 => 3, 3 => 2]);
});

test('already distributed students keep their section number', function () {
    $world = schedulingWorld(2);
    configureStudentsPerSection($world, 5);
    $before = Student::orderBy('id')->pluck('section_number', 'id')->all();

    expect(distributionService()->distribute($world['department']->id, $world['level']->id))->toBe(0)
        ->and(Student::orderBy('id')->pluck('section_number', 'id')->all())->toBe($before);
});

test('inactive students are never distributed', function () {
    $world = schedulingWorld(0);
    configureStudentsPerSection($world, 5);
    seedSectionStudents(null, 2, $world);
    Student::first()->update(['status' => StudentStatus::WITHDRAWN]);

    expect(distributionService()->distribute($world['department']->id, $world['level']->id))->toBe(1);
    expect(Student::whereNull('section_number')->count())->toBe(1);
});

test('distribution requires a configured section size', function () {
    $world = schedulingWorld(0);
    seedSectionStudents(null, 2, $world);

    expect(fn () => distributionService()->distribute($world['department']->id, $world['level']->id))
        ->toThrow(InvalidArgumentException::class, 'عدد الطلاب في السكشن');
});

test('changing level or department clears the section number', function () {
    $world = schedulingWorld(1);
    $student = Student::first();

    $sameDepartment = Section::create(['name' => 'شعبة أخرى', 'department_id' => $world['department']->id]);
    $student->update(['section_id' => $sameDepartment->id]);
    expect($student->fresh()->section_number)->toBe(1);

    $student->update(['level_id' => Level::create(['name' => 'الفرقة الثانية'])->id]);
    expect($student->fresh()->section_number)->toBeNull();
});

test('settings screen distributes students for the active department and level', function () {
    $world = schedulingWorld(0);
    configureStudentsPerSection($world, 2);
    seedSectionStudents(null, 5, $world);

    Permission::firstOrCreate(['name' => 'registration_fees.edit', 'guard_name' => 'web']);
    $world['admin']->givePermissionTo('registration_fees.edit');

    Livewire::actingAs($world['admin'])
        ->test(Index::class)
        ->call('setDepartment', $world['department']->id)
        ->call('setLevel', $world['level']->id)
        ->assertSee('توزيع الطلاب على السكاشن')
        ->call('distributeStudents')
        ->assertSee('تم توزيع 5 طالب على السكاشن بنجاح.');

    expect(Student::whereNull('section_number')->count())->toBe(0)
        ->and(distributionService()->sectionsCount($world['department']->id, $world['level']->id))->toBe(3);
});

test('settings distribution is forbidden without edit permission', function () {
    $world = schedulingWorld(0);

    Livewire::actingAs($world['admin'])
        ->test(Index::class)
        ->call('distributeStudents')
        ->assertForbidden();
});
