<?php

use App\Enums\DiscountMode;
use App\Enums\DiscountScope;
use App\Enums\Semester;
use App\Enums\SemesterStatus;
use App\Livewire\Admin\AdditionalFee\Index as AdditionalFeeIndex;
use App\Livewire\Admin\Finance\Discounts\Index as DiscountsIndex;
use App\Livewire\Admin\LectureSchedule\Index as LectureScheduleIndex;
use App\Models\AdditionalFee;
use App\Models\StudentDiscount;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function grantPermissions($user, array $names): void
{
    foreach ($names as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    $user->givePermissionTo($names);
}

test('a new additional fee is stored for the current year and semester', function () {
    $world = billingWorld();
    grantPermissions($world['admin'], ['additional_fees.view', 'additional_fees.create']);

    Livewire::actingAs($world['admin'])
        ->test(AdditionalFeeIndex::class)
        ->assertSet('semester', Semester::FIRST->value)
        ->call('create')
        ->set('name', 'رسوم كارنيه')
        ->set('amount', 100)
        ->call('save')
        ->assertHasNoErrors();

    $fee = AdditionalFee::firstOrFail();

    expect($fee->year_id)->toBe($world['year']->id)
        ->and($fee->semester)->toBe(Semester::FIRST);
});

test('editing an additional fee keeps its original term', function () {
    $world = billingWorld();
    grantPermissions($world['admin'], ['additional_fees.view', 'additional_fees.edit']);

    $oldYear = Year::create([
        'year' => '2020-2021',
        'first_semester_status' => SemesterStatus::DISABLED,
        'second_semester_status' => SemesterStatus::DISABLED,
        'summer_semester_status' => SemesterStatus::DISABLED,
    ]);
    $fee = AdditionalFee::create(['name' => 'قديم', 'amount' => 50, 'gender' => 'both', 'is_one_time' => true, 'year_id' => $oldYear->id, 'semester' => Semester::SECOND->value]);

    Livewire::actingAs($world['admin'])
        ->test(AdditionalFeeIndex::class)
        ->call('edit', $fee->id)
        ->assertSee('2020-2021')
        ->set('name', 'قديم معدل')
        ->call('save');

    expect($fee->fresh()->year_id)->toBe($oldYear->id)
        ->and($fee->fresh()->semester)->toBe(Semester::SECOND);
});

test('the additional fee semester cannot be picked from the browser', function () {
    $world = billingWorld();
    grantPermissions($world['admin'], ['additional_fees.view', 'additional_fees.create']);

    Livewire::actingAs($world['admin'])
        ->test(AdditionalFeeIndex::class)
        ->set('semester', Semester::SUMMER->value);
})->throws(CannotUpdateLockedPropertyException::class);

test('a granted discount is scoped to the current term whatever the form holds', function () {
    $world = billingWorld();
    grantPermissions($world['admin'], ['discounts.view', 'discounts.create']);

    Livewire::actingAs($world['admin'])
        ->test(DiscountsIndex::class)
        ->call('create')
        ->assertSet('form.year_id', $world['year']->id)
        ->assertSet('form.semester', Semester::FIRST->value)
        ->set('form', [
            'student_id' => $world['student']->id,
            'scope' => DiscountScope::Registration->value,
            'fee_id' => null,
            'year_id' => null,
            'semester' => Semester::SUMMER->value,
            'mode' => DiscountMode::Fixed->value,
            'value' => '500',
            'reason' => 'شهادة تكريم',
            'decision_number' => 'قرار-١',
        ])
        ->call('save')
        ->assertHasNoErrors();

    $discount = StudentDiscount::firstOrFail();

    expect($discount->year_id)->toBe($world['year']->id)
        ->and($discount->semester)->toBe(Semester::FIRST);
});

test('the lecture schedule screen is fixed to the current term courses', function () {
    $world = schedulingWorld();

    Livewire::actingAs($world['admin'])
        ->test(LectureScheduleIndex::class)
        ->assertSet('semester', 'الأول')
        ->assertSee($world['year']->year);
});
