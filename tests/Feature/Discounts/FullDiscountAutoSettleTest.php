<?php

use App\Enums\DiscountMode;
use App\Enums\DiscountScope;
use App\Enums\Semester;
use App\Livewire\Admin\Finance\Discounts\Index;
use App\Livewire\Admin\Finance\FeeIssuance;
use App\Models\AdditionalFee;
use App\Models\RegistrationFee;
use App\Models\StudentFeeTicket;
use App\Models\WalletTransaction;
use App\Services\DiscountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->world = billingWorld();

    foreach (['discounts.view', 'discounts.create', 'finance.view', 'finance.create'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
    $this->world['admin']->givePermissionTo(['discounts.view', 'discounts.create', 'finance.view', 'finance.create']);
});

function autoSettleFee(array $world): AdditionalFee
{
    $fee = AdditionalFee::create([
        'name' => 'بطاقة دراسية',
        'amount' => 300,
        'gender' => 'both',
        'is_one_time' => false,
        'year_id' => $world['year']->id,
        'semester' => Semester::FIRST->value,
    ]);
    $fee->departments()->attach($world['department']->id);
    $fee->levels()->attach($world['level']->id);
    $fee->sections()->attach($world['section']->id);

    return $fee;
}

function grantThroughScreen(array $world, array $overrides = []): void
{
    Livewire::actingAs($world['admin'])
        ->test(Index::class)
        ->call('create')
        ->set('form', array_merge([
            'student_id' => $world['student']->id,
            'scope' => DiscountScope::Registration->value,
            'fee_id' => null,
            'mode' => DiscountMode::Percentage->value,
            'value' => '100',
            'reason' => 'إعفاء كامل',
            'decision_number' => null,
        ], $overrides))
        ->call('save')
        ->assertHasNoErrors();
}

it('issues and settles a fully discounted additional fee when the discount is granted', function () {
    $fee = autoSettleFee($this->world);

    grantThroughScreen($this->world, ['scope' => DiscountScope::Additional->value, 'fee_id' => $fee->id]);

    $ticket = StudentFeeTicket::where('fee_type', 'additional')->where('fee_id', $fee->id)->firstOrFail();

    expect($ticket->status)->toBe('paid')
        ->and($ticket->paid_at)->not->toBeNull()
        ->and($ticket->payment_method)->toBe('discount')
        ->and((float) $ticket->original_amount)->toBe(300.0)
        ->and((float) $ticket->discount_amount)->toBe(300.0)
        ->and((float) $ticket->amount)->toBe(0.0)
        ->and($ticket->notes)->toContain('خصم كامل')
        ->and($ticket->ministerial_receipt_number)->toBeNull();

    expect(WalletTransaction::count())->toBe(0);
});

it('issues and settles the registration fee when a fixed discount covers it entirely', function () {
    grantThroughScreen($this->world, ['mode' => DiscountMode::Fixed->value, 'value' => '2000']);

    $ticket = StudentFeeTicket::where('fee_type', 'registration')->firstOrFail();

    expect($ticket->status)->toBe('paid')
        ->and((float) $ticket->amount)->toBe(0.0)
        ->and($ticket->discountUsages()->count())->toBe(1);
});

it('does not auto-issue when the discount covers only part of the fee', function () {
    grantThroughScreen($this->world, ['mode' => DiscountMode::Fixed->value, 'value' => '500']);

    expect(StudentFeeTicket::count())->toBe(0);
});

it('does not auto-issue registration while additional fees are still uninvoiced', function () {
    autoSettleFee($this->world);

    grantThroughScreen($this->world);

    expect(StudentFeeTicket::count())->toBe(0);
});

it('does not auto-issue for an any-scope discount', function () {
    grantThroughScreen($this->world, ['scope' => DiscountScope::Any->value]);

    expect(StudentFeeTicket::count())->toBe(0);
});

it('settles an existing pending ticket the new discount fully covers', function () {
    Livewire::actingAs($this->world['admin'])
        ->test(FeeIssuance::class)
        ->set('studentCode', $this->world['student']->username)
        ->call('searchStudent')
        ->set('selectedFees', ['registration-'.RegistrationFee::firstOrFail()->id])
        ->call('generateTickets');

    $ticket = StudentFeeTicket::where('fee_type', 'registration')->firstOrFail();
    expect($ticket->status)->toBe('pending');

    grantThroughScreen($this->world);

    expect($ticket->refresh()->status)->toBe('paid')
        ->and((float) $ticket->amount)->toBe(0.0)
        ->and(StudentFeeTicket::count())->toBe(1);
});

it('marks a ticket paid at issuance when existing discounts cover it fully', function () {
    app(DiscountService::class)->grant([
        'student_id' => $this->world['student']->id,
        'scope' => DiscountScope::Any->value,
        'mode' => DiscountMode::Percentage->value,
        'value' => '100',
        'reason' => 'إعفاء كامل',
    ], $this->world['admin']);

    Livewire::actingAs($this->world['admin'])
        ->test(FeeIssuance::class)
        ->set('studentCode', $this->world['student']->username)
        ->call('searchStudent')
        ->set('selectedFees', ['registration-'.RegistrationFee::firstOrFail()->id])
        ->call('generateTickets');

    $ticket = StudentFeeTicket::where('fee_type', 'registration')->firstOrFail();

    expect($ticket->status)->toBe('paid')
        ->and($ticket->payment_method)->toBe('discount');
});
