<?php

use App\Livewire\Admin\YearSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function yearSettingsAdmin(): User
{
    Permission::firstOrCreate(['name' => 'years.edit', 'guard_name' => 'web']);

    $user = User::factory()->create();
    $user->givePermissionTo('years.edit');

    return $user;
}

test('exam window save reports through the toast bridge and persists', function () {
    $world = examWorld();
    $user = yearSettingsAdmin();

    Livewire::actingAs($user)
        ->test(YearSettings::class)
        ->set('selectedYearId', $world['year']->id)
        ->call('selectYear', $world['year']->id)
        ->set('first_semester_exam_from', '2026-01-01')
        ->set('first_semester_exam_to', '2026-01-31')
        ->call('updateExamWindows')
        ->assertDispatched('toast', fn (string $name, array $params) => ($params[0]['message'] ?? null) === 'تم تحديث فترات الامتحانات بنجاح'
            && ($params[0]['type'] ?? null) === 'success');

    expect($world['year']->fresh()->semesterExamWindow(\App\Enums\Semester::FIRST))
        ->toBe(['from' => '2026-01-01', 'to' => '2026-01-31']);
});

test('an exam window with the end before the start is rejected', function () {
    $world = examWorld();
    $user = yearSettingsAdmin();

    Livewire::actingAs($user)
        ->test(YearSettings::class)
        ->call('selectYear', $world['year']->id)
        ->set('first_semester_exam_from', '2026-02-01')
        ->set('first_semester_exam_to', '2026-01-01')
        ->call('updateExamWindows')
        ->assertHasErrors('first_semester_exam_to');

    expect($world['year']->fresh()->semesterExamWindow(\App\Enums\Semester::FIRST))->toBeNull();
});
