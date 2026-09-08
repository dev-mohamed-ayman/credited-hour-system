<?php

use App\Enums\DiscountMode;
use App\Enums\DiscountScope;
use App\Enums\DiscountStatus;
use App\Livewire\Admin\Finance\Discounts\Index;
use App\Models\StudentDiscount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->world = billingWorld();

    foreach (['discounts.view', 'discounts.create', 'discounts.edit', 'discounts.revoke'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
});

function grantForm(array $world, array $overrides = []): array
{
    return array_merge([
        'student_id' => $world['student']->id,
        'scope' => DiscountScope::Registration->value,
        'fee_id' => null,
        'year_id' => null,
        'semester' => null,
        'mode' => DiscountMode::Fixed->value,
        'value' => '500',
        'reason' => 'شهادة تكريم',
        'decision_number' => 'قرار-١٢٣',
    ], $overrides);
}

it('grants a discount through the screen with full provenance', function () {
    $this->world['admin']->givePermissionTo(['discounts.view', 'discounts.create']);

    Livewire::actingAs($this->world['admin'])
        ->test(Index::class)
        ->call('create')
        ->set('form', grantForm($this->world))
        ->call('save')
        ->assertHasNoErrors();

    $discount = StudentDiscount::firstOrFail();
    expect($discount->status)->toBe(DiscountStatus::Active)
        ->and((float) $discount->remaining_amount)->toBe(500.0)
        ->and($discount->created_by)->toBe($this->world['admin']->id)
        ->and($discount->events()->where('action', 'granted')->count())->toBe(1);
});

it('rejects invalid discount definitions with Arabic messages', function () {
    $this->world['admin']->givePermissionTo(['discounts.view', 'discounts.create']);

    Livewire::actingAs($this->world['admin'])
        ->test(Index::class)
        ->call('create')
        ->set('form', grantForm($this->world, ['value' => '0']))
        ->call('save')
        ->assertHasErrors(['form.value' => 'min'])
        ->set('form', grantForm($this->world, ['mode' => DiscountMode::Percentage->value, 'value' => '150']))
        ->call('save')
        ->assertHasErrors(['form.value' => 'max'])
        ->set('form', grantForm($this->world, ['scope' => DiscountScope::Additional->value, 'fee_id' => null]))
        ->call('save')
        ->assertHasErrors(['form.fee_id' => 'required'])
        ->set('form', grantForm($this->world, ['reason' => '']))
        ->call('save')
        ->assertHasErrors(['form.reason' => 'required']);
});

it('denies the screen without discounts.view', function () {
    Livewire::actingAs($this->world['admin'])->test(Index::class)->assertStatus(403);
});

it('denies saving without discounts.create', function () {
    $this->world['admin']->givePermissionTo(['discounts.view']);

    Livewire::actingAs($this->world['admin'])
        ->test(Index::class)
        ->set('form', grantForm($this->world))
        ->call('save')
        ->assertStatus(403);

    expect(StudentDiscount::count())->toBe(0);
});

it('lists granted discounts with student, value and status', function () {
    $this->world['admin']->givePermissionTo(['discounts.view', 'discounts.create']);

    Livewire::actingAs($this->world['admin'])
        ->test(Index::class)
        ->call('create')
        ->set('form', grantForm($this->world))
        ->call('save')
        ->assertSee('شهادة تكريم')
        ->assertSee('نشط')
        ->assertSee('قرار-١٢٣');
});

it('filters the list by year and semester', function () {
    $this->world['admin']->givePermissionTo(['discounts.view']);

    StudentDiscount::factory()->create([
        'student_id' => $this->world['student']->id,
        'semester' => \App\Enums\Semester::FIRST->value,
        'reason' => 'منح الترم الأول س',
        'created_by' => $this->world['admin']->id,
    ]);
    StudentDiscount::factory()->create([
        'student_id' => $this->world['student']->id,
        'semester' => \App\Enums\Semester::SECOND->value,
        'reason' => 'منح الترم الثاني ص',
        'created_by' => $this->world['admin']->id,
    ]);

    Livewire::actingAs($this->world['admin'])
        ->test(Index::class)
        ->set('semesterFilter', \App\Enums\Semester::FIRST->value)
        ->assertSee('منح الترم الأول س')
        ->assertDontSee('منح الترم الثاني ص');
});

it('filters the list by student search', function () {
    $this->world['admin']->givePermissionTo(['discounts.view']);

    StudentDiscount::factory()->create([
        'student_id' => $this->world['student']->id,
        'reason' => 'حالة إنسانية مميزة',
        'created_by' => $this->world['admin']->id,
    ]);
    StudentDiscount::factory()->create([
        'student_id' => $this->world['student']->id,
        'reason' => 'خصم غير ذي صلة',
        'created_by' => $this->world['admin']->id,
    ]);

    Livewire::actingAs($this->world['admin'])
        ->test(Index::class)
        ->set('search', $this->world['student']->username)
        ->assertSee('حالة إنسانية مميزة');
});
