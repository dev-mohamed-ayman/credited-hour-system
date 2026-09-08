<?php

use App\Enums\DiscountMode;
use App\Enums\DiscountScope;
use App\Enums\DiscountStatus;
use App\Livewire\Admin\Finance\FeeIssuance;
use App\Models\RegistrationFee;
use App\Models\StudentFeeTicket;
use App\Services\DiscountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->world = billingWorld();

    foreach (['finance.view', 'finance.create'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
    $this->world['admin']->givePermissionTo(['finance.view', 'finance.create']);

    $this->service = app(DiscountService::class);
    $this->fee = RegistrationFee::firstOrFail();
});

function issuanceGrant(DiscountService $service, $world, array $overrides = []): \App\Models\StudentDiscount
{
    return $service->grant(array_merge([
        'student_id' => $world['student']->id,
        'scope' => DiscountScope::Registration->value,
        'mode' => DiscountMode::Fixed->value,
        'value' => '500.00',
        'reason' => 'منحة تفوق',
    ], $overrides), $world['admin']);
}

it('applies an eligible discount inside the issuance transaction', function () {
    issuanceGrant($this->service, $this->world);

    Livewire::actingAs($this->world['admin'])
        ->test(FeeIssuance::class)
        ->set('studentCode', $this->world['student']->username)
        ->call('searchStudent')
        ->set('selectedFees', ['registration-'.$this->fee->id])
        ->call('generateTickets');

    $ticket = StudentFeeTicket::where('fee_type', 'registration')->firstOrFail();

    expect((float) $ticket->original_amount)->toBe(2000.0)
        ->and((float) $ticket->discount_amount)->toBe(500.0)
        ->and((float) $ticket->amount)->toBe(1500.0);

    expect($ticket->discountUsages()->count())->toBe(1);
    expect($this->service->eligibleFor($this->world['student'], 'registration'))->toBeEmpty();
});

it('shows an available-discount badge with expected net before generating', function () {
    issuanceGrant($this->service, $this->world);

    Livewire::actingAs($this->world['admin'])
        ->test(FeeIssuance::class)
        ->set('studentCode', $this->world['student']->username)
        ->call('searchStudent')
        ->assertSee('خصم متاح')
        ->assertSee('1,500.00');
});

it('issues discount-free tickets unchanged (no regression, SC-003)', function () {
    Livewire::actingAs($this->world['admin'])
        ->test(FeeIssuance::class)
        ->set('studentCode', $this->world['student']->username)
        ->call('searchStudent')
        ->set('selectedFees', ['registration-'.$this->fee->id])
        ->call('generateTickets');

    $ticket = StudentFeeTicket::where('fee_type', 'registration')->firstOrFail();

    expect($ticket->original_amount)->toBeNull()
        ->and((float) $ticket->discount_amount)->toBe(0.0)
        ->and((float) $ticket->amount)->toBe(2000.0);
});

it('does not apply a revoked discount at issuance', function () {
    $discount = issuanceGrant($this->service, $this->world);
    $discount->forceFill(['status' => DiscountStatus::Revoked])->save();

    Livewire::actingAs($this->world['admin'])
        ->test(FeeIssuance::class)
        ->set('studentCode', $this->world['student']->username)
        ->call('searchStudent')
        ->set('selectedFees', ['registration-'.$this->fee->id])
        ->call('generateTickets');

    $ticket = StudentFeeTicket::where('fee_type', 'registration')->firstOrFail();
    expect((float) $ticket->amount)->toBe(2000.0)->and($ticket->original_amount)->toBeNull();
});

it('shows the available-discount badge on other-fee template rows', function () {
    \App\Models\FeeTemplate::create(['name' => 'أوراق امتحان', 'amount' => 250, 'is_active' => true, 'created_by_user_id' => $this->world['admin']->id]);
    issuanceGrant($this->service, $this->world, ['scope' => DiscountScope::Any->value, 'value' => '100.00']);

    Livewire::actingAs($this->world['admin'])
        ->test(FeeIssuance::class)
        ->set('studentCode', $this->world['student']->username)
        ->call('searchStudent')
        ->set('otherFees', [['id' => 'tpl-1', 'name' => 'أوراق امتحان', 'amount' => 250]])
        ->assertSee('خصم متاح')
        ->assertSee('150.00');
});

it('applies an any-scope discount to an other-fee ticket at issuance', function () {
    \App\Models\FeeTemplate::create(['name' => 'أوراق امتحان', 'amount' => 250, 'is_active' => true, 'created_by_user_id' => $this->world['admin']->id]);
    issuanceGrant($this->service, $this->world, ['scope' => DiscountScope::Any->value, 'value' => '100.00']);

    Livewire::actingAs($this->world['admin'])
        ->test(FeeIssuance::class)
        ->set('studentCode', $this->world['student']->username)
        ->call('searchStudent')
        ->set('otherFees', [['id' => 'tpl-2', 'name' => 'أوراق امتحان', 'amount' => 250]])
        ->set('selectedFees', ['other-tpl-2'])
        ->call('generateTickets');

    $ticket = StudentFeeTicket::where('fee_type', 'other')->firstOrFail();
    expect((float) $ticket->original_amount)->toBe(250.0)
        ->and((float) $ticket->discount_amount)->toBe(100.0)
        ->and((float) $ticket->amount)->toBe(150.0);
});
