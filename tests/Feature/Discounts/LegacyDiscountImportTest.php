<?php

use App\Enums\DiscountScope;
use App\Enums\DiscountStatus;
use App\Models\AdditionalFee;
use App\Models\StudentDiscount;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->world = billingWorld();

    Schema::create('students_discounts', function (Blueprint $table) {
        $table->id();
        $table->string('student_code');
        $table->string('year')->nullable();
        $table->string('semester')->nullable();
        $table->string('type');
        $table->decimal('amount', 10, 2);
        $table->string('reason')->nullable();
        $table->string('created_by')->nullable();
        $table->timestamps();
    });

    AdditionalFee::create(['name' => 'مصروفات إدارية', 'amount' => 300, 'gender' => 'both']);
});

function legacyRow(string $code, array $overrides = []): void
{
    DB::table('students_discounts')->insert(array_merge([
        'student_code' => $code,
        'year' => '2025-2026',
        'semester' => 'الأول',
        'type' => 'دراسية',
        'amount' => 500,
        'reason' => 'إرث قديم',
        'created_by' => 'admin',
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));
}

it('imports legacy study/admin rows and skips wallet-gift and dead-code types', function () {
    $code = $this->world['student']->username;
    $adminFee = AdditionalFee::firstOrFail();

    legacyRow($code, ['type' => 'دراسية', 'amount' => 400, 'reason' => 'منحة إرث']);
    legacyRow($code, ['type' => 'اخرى', 'amount' => 150, 'reason' => 'رسوم متنوعة إرث']);
    legacyRow($code, ['type' => 'ادارية', 'amount' => 300, 'reason' => 'إعفاء إداري إرث']);
    legacyRow($code, ['type' => 'محفظة', 'amount' => 250]);
    legacyRow($code, ['type' => 'خدمات تعليمية', 'amount' => 100]);

    $this->artisan('discounts:import-legacy')
        ->expectsOutputToContain('نشط')
        ->assertSuccessful();

    expect(StudentDiscount::count())->toBe(3);

    $study = StudentDiscount::where('reason', 'منحة إرث')->firstOrFail();
    $other = StudentDiscount::where('reason', 'رسوم متنوعة إرث')->firstOrFail();
    $admin = StudentDiscount::where('reason', 'إعفاء إداري إرث')->firstOrFail();

    expect($study->scope)->toBe(DiscountScope::Registration)
        ->and($other->scope)->toBe(DiscountScope::Additional)
        ->and($other->fee_id)->toBeNull()
        ->and((float) $other->remaining_amount)->toBe(150.0)
        ->and($admin->scope)->toBe(DiscountScope::Additional)
        ->and((int) $admin->fee_id)->toBe($adminFee->id);
});

it('marks migrated discounts with granted events carrying legacy provenance', function () {
    $code = $this->world['student']->username;
    legacyRow($code, ['type' => 'دراسية', 'amount' => '400.00', 'reason' => 'منحة قديمة']);

    $this->artisan('discounts:import-legacy')->assertSuccessful();

    $discount = StudentDiscount::where('reason', 'منحة قديمة')->firstOrFail();
    $event = $discount->events()->where('action', 'granted')->firstOrFail();

    expect($event->meta['source'])->toBe('legacy-migration')
        ->and($discount->status)->toBe(DiscountStatus::Active)
        ->and($discount->created_by)->toBeNull();
});

it('reports skipped legacy types in the summary', function () {
    $code = $this->world['student']->username;
    legacyRow($code, ['type' => 'محفظة', 'amount' => 900]);

    $this->artisan('discounts:import-legacy')
        ->expectsOutputToContain('محفظة')
        ->assertSuccessful();

    expect(StudentDiscount::count())->toBe(0);
});

it('ignores unknown student codes with a report, not a crash', function () {
    legacyRow('GHOST999', ['type' => 'دراسية']);

    $this->artisan('discounts:import-legacy')
        ->expectsOutputToContain('GHOST999')
        ->assertSuccessful();

    expect(StudentDiscount::count())->toBe(0);
});
