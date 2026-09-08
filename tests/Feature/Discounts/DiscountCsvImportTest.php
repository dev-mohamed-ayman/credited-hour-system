<?php

use App\Enums\DiscountMode;
use App\Enums\DiscountScope;
use App\Livewire\Admin\Finance\Discounts\Index;
use App\Models\StudentDiscount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->world = billingWorld();

    foreach (['discounts.view', 'discounts.create'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
    $this->world['admin']->givePermissionTo(['discounts.view', 'discounts.create']);
});

function csvFile(string $content): UploadedFile
{
    return UploadedFile::fake()->createWithContent('discounts.csv', $content);
}

it('imports a valid CSV as fixed discounts scoped to the current term', function () {
    $code = $this->world['student']->username;
    $csv = "كود الطالب,نوع الرسوم,قيمة الخصم,سبب الخصم\n"
        .implode("\n", array_map(fn ($a) => "{$code},رسوم التسجيل,{$a},منحة تفوق", [100, 200, 300, 400, 500]));

    Livewire::actingAs($this->world['admin'])
        ->test(Index::class)
        ->set('importFile', csvFile($csv))
        ->call('importCsv')
        ->assertHasNoErrors();

    expect(StudentDiscount::count())->toBe(5);

    $discount = StudentDiscount::first();
    expect($discount->mode)->toBe(DiscountMode::Fixed)
        ->and($discount->scope)->toBe(DiscountScope::Registration)
        ->and($discount->year_id)->toBe($this->world['year']->id)
        ->and($discount->created_by)->toBe($this->world['admin']->id);
});

it('tolerates legacy header spelling variants and BOM', function () {
    $code = $this->world['student']->username;
    $csv = "\xEF\xBB\xBFكود الطالب , نوع الخصم,قيمه الخصم ,سبب الخصم\n{$code},رسوم التسجيل,150,تسامح ترويسات";

    Livewire::actingAs($this->world['admin'])
        ->test(Index::class)
        ->set('importFile', csvFile($csv))
        ->call('importCsv')
        ->assertHasNoErrors();

    expect(StudentDiscount::count())->toBe(1);
});

it('rejects the whole file when one row references an unknown student', function () {
    $code = $this->world['student']->username;
    $csv = "كود الطالب,نوع الرسوم,قيمة الخصم,سبب الخصم\n"
        ."{$code},رسوم التسجيل,100,سليم\n"
        ."{$code},رسوم التسجيل,200,سليم\n"
        .'NOPE999,رسوم التسجيل,300,سطر تالف';

    Livewire::actingAs($this->world['admin'])
        ->test(Index::class)
        ->set('importFile', csvFile($csv))
        ->call('importCsv');

    expect(StudentDiscount::count())->toBe(0);
});

it('rejects negative amounts', function () {
    $code = $this->world['student']->username;
    $csv = "كود الطالب,نوع الرسوم,قيمة الخصم,سبب الخصم\n{$code},رسوم التسجيل,-50,سالب";

    Livewire::actingAs($this->world['admin'])
        ->test(Index::class)
        ->set('importFile', csvFile($csv))
        ->call('importCsv');

    expect(StudentDiscount::count())->toBe(0);
});

it('denies CSV import without discounts.create', function () {
    $bare = \App\Models\User::factory()->create();
    $bare->givePermissionTo('discounts.view');

    $code = $this->world['student']->username;
    $csv = "كود الطالب,نوع الرسوم,قيمة الخصم,سبب الخصم\n{$code},رسوم التسجيل,100,سليم";

    Livewire::actingAs($bare)
        ->test(Index::class)
        ->set('importFile', csvFile($csv))
        ->call('importCsv')
        ->assertStatus(403);
});
