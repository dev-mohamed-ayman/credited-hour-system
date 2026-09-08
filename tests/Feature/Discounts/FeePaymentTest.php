<?php

use App\Livewire\Admin\Finance\FeePayment;
use App\Models\DailyPaymentDateTime;
use App\Models\Setting;
use App\Models\StudentFeeTicket;
use App\Models\WalletTransaction;
use App\Services\RegistrationBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->world = billingWorld();

    foreach (['finance.view', 'finance.edit'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
    $this->world['admin']->givePermissionTo(['finance.view', 'finance.edit']);

    Setting::query()->firstOrCreate([])->update([
        'ministerial_receipt_current' => 100,
        'ministerial_receipt_end' => 110,
    ]);
    DailyPaymentDateTime::create(['date' => now()->toDateString(), 'start_date' => now()]);
});

function payTicket(\Livewire\Features\SupportTesting\Testable $component, StudentFeeTicket $t): \Livewire\Features\SupportTesting\Testable
{
    return $component->set('ticketNumber', $t->ticket_number)->call('searchTicket')
        ->set('paymentMethod', 'cash')
        ->call('confirmPayment');
}

it('confirms a zero-net discount ticket without consuming a ministerial receipt number', function () {
    $ticket = issueTicket($this->world['student'], $this->world['year'], 0.0);
    $ticket->update(['fee_name' => 'مصاريف تسجيل', 'original_amount' => 500, 'discount_amount' => 500]);

    payTicket(Livewire::actingAs($this->world['admin'])->test(FeePayment::class), $ticket);

    $ticket->refresh();
    expect($ticket->status)->toBe('paid')
        ->and($ticket->ministerial_receipt_number)->toBeNull()
        ->and((int) Setting::first()->ministerial_receipt_current)->toBe(100);

    expect(WalletTransaction::where('reference_type', $ticket->getMorphClass())->where('reference_id', $ticket->id)->get())
        ->toHaveCount(0);
});

it('annotates a full-discount payment without a zero-value wallet deposit', function () {
    $ticket = issueTicket($this->world['student'], $this->world['year'], 0.0);
    $ticket->update(['original_amount' => 1000, 'discount_amount' => 1000]);

    payTicket(Livewire::actingAs($this->world['admin'])->test(FeePayment::class), $ticket);

    expect((float) $ticket->refresh()->amount)->toBe(0.0)
        ->and(WalletTransaction::where('reference_id', $ticket->id)->where('amount', 0)->count())->toBe(0)
        ->and($ticket->notes)->toContain('خصم كامل');
});

it('BR-13: a confirmed zero-net ticket is fully paid and does not block the fee gate', function () {
    $ticket = issueTicket($this->world['student'], $this->world['year'], 0.0);
    $ticket->update(['original_amount' => 700, 'discount_amount' => 700]);

    $billing = app(RegistrationBillingService::class);
    expect($billing->checkFeeGate($this->world['student'])['allowed'])->toBeFalse();

    payTicket(Livewire::actingAs($this->world['admin'])->test(FeePayment::class), $ticket);

    expect($billing->outstandingTotal($this->world['student']))->toBe(0.0)
        ->and($billing->checkFeeGate($this->world['student'])['allowed'])->toBeTrue();
});

it('still consumes a ministerial number for positive registration tickets', function () {
    $ticket = issueTicket($this->world['student'], $this->world['year'], 2000.0);

    payTicket(Livewire::actingAs($this->world['admin'])->test(FeePayment::class), $ticket);

    expect((int) $ticket->refresh()->ministerial_receipt_number)->toBe(101)
        ->and((int) Setting::first()->ministerial_receipt_current)->toBe(101);
});

it('mixed zero and positive selection consumes numbers only for the positive one', function () {
    $zero = issueTicket($this->world['student'], $this->world['year'], 0.0);
    $zero->update(['original_amount' => 500, 'discount_amount' => 500]);
    $positive = issueTicket($this->world['student'], $this->world['year'], 2000.0);

    Livewire::actingAs($this->world['admin'])
        ->test(FeePayment::class)
        ->set('showForm', true)
        ->set('tickets', [$zero->fresh(), $positive->fresh()])
        ->set('selectedTickets', [$zero->id, $positive->id])
        ->set('paymentMethod', 'cash')
        ->call('confirmPayment');

    $zero->refresh();
    $positive->refresh();
    expect($zero->status)->toBe('paid')->and($zero->ministerial_receipt_number)->toBeNull()
        ->and($positive->status)->toBe('paid')->and((int) $positive->ministerial_receipt_number)->toBe(101);
});
