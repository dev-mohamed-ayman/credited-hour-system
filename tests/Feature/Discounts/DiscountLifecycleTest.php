<?php

use App\Enums\DiscountEventAction;
use App\Enums\DiscountMode;
use App\Enums\DiscountScope;
use App\Enums\DiscountStatus;
use App\Exceptions\DiscountEditException;
use App\Exceptions\DiscountRevokeException;
use App\Livewire\Admin\Finance\Discounts\Index;
use App\Livewire\Admin\Finance\FeeIssuance;
use App\Models\StudentDiscount;
use App\Models\StudentFeeTicket;
use App\Services\DiscountService;
use App\Services\StudentTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->world = transferWorld();
    $this->service = app(DiscountService::class);

    foreach (['discounts.view', 'discounts.create', 'discounts.edit', 'discounts.revoke'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
});

function lifecycleGrant(DiscountService $service, $world, array $overrides = []): StudentDiscount
{
    return $service->grant(array_merge([
        'student_id' => $world['student']->id,
        'scope' => DiscountScope::Registration->value,
        'mode' => DiscountMode::Fixed->value,
        'value' => '500.00',
        'reason' => 'خصم lifecycle',
    ], $overrides), $world['admin']);
}

function applyTo(StudentDiscount $discount, \App\Models\StudentFeeTicket $ticket, $world, DiscountService $service): void
{
    $plan = $service->planApplication(collect([$discount->refresh()]), (string) $ticket->amount);
    $service->applyToTicket($ticket, $plan['applied'], $world['admin']);
}

// ── concurrency / double-spend ─────────────────────────────────────────────

it('lets only one of two interleaved applications consume the discount', function () {
    $discount = lifecycleGrant($this->service, $this->world, ['value' => '300.00']);
    $ticket = issueTicket($this->world['student'], $this->world['year'], 300.00);

    // Request A and B both planned 300 off the same balance; A commits first.
    $this->service->applyToTicket($ticket, [$discount->id => '300.00'], $this->world['admin']);
    // Request B runs with the same stale plan after A committed.
    $ticket2 = issueTicket($this->world['student'], $this->world['year'], 300.00);
    $this->service->applyToTicket($ticket2, [$discount->id => '300.00'], $this->world['admin']);

    expect($discount->refresh()->status)->toBe(DiscountStatus::Exhausted)
        ->and($discount->usages()->count())->toBe(1)
        ->and((float) $ticket->refresh()->amount)->toBe(0.0)
        ->and((float) $ticket2->refresh()->amount)->toBe(300.0);
});

it('cannot apply the same discount twice to one ticket', function () {
    $discount = lifecycleGrant($this->service, $this->world, ['value' => '1200.00']);
    $ticket = issueTicket($this->world['student'], $this->world['year'], 500.00);

    $this->service->applyToTicket($ticket, [$discount->id => '500.00'], $this->world['admin']);
    $this->service->applyToTicket($ticket, [$discount->id => '500.00'], $this->world['admin']);

    $ticket->refresh();
    expect($ticket->discountUsages()->count())->toBe(1)
        ->and((float) $ticket->discount_amount)->toBe(500.0)
        ->and((float) $ticket->amount)->toBe(0.0);
});

// ── revocation ─────────────────────────────────────────────────────────────

it('revokes the remaining balance and keeps recorded applications as history', function () {
    $discount = lifecycleGrant($this->service, $this->world);
    $ticket = issueTicket($this->world['student'], $this->world['year'], 200.00);
    applyTo($discount, $ticket, $this->world, $this->service);

    $this->service->revoke($discount, 'قرار سحب', $this->world['admin']);

    $discount->refresh();
    expect($discount->status)->toBe(DiscountStatus::Revoked)
        ->and($discount->revoked_reason)->toBe('قرار سحب')
        ->and($discount->revoked_by)->toBe($this->world['admin']->id)
        ->and($discount->usages()->count())->toBe(1)
        ->and((float) $discount->remaining_amount)->toBe(300.0);

    expect($discount->events()->where('action', DiscountEventAction::Revoked->value)->exists())->toBeTrue();
});

it('rejects revoking an exhausted discount and points to manual settlement', function () {
    $discount = lifecycleGrant($this->service, $this->world);
    $ticket = issueTicket($this->world['student'], $this->world['year'], 500.00);
    $ticket->update(['status' => 'paid', 'paid_at' => now()]);
    applyTo($discount, $ticket, $this->world, $this->service);

    $this->service->revoke($discount->refresh(), 'محاولة', $this->world['admin']);
})->throws(DiscountRevokeException::class, 'بتسوية يدوية موثقة');

it('requires a revoke reason', function () {
    $discount = lifecycleGrant($this->service, $this->world);

    $this->service->revoke($discount, '   ', $this->world['admin']);
})->throws(DiscountRevokeException::class, 'إلزامي');

it('still revokes the unused remainder when a partial application sits on a paid ticket', function () {
    $discount = lifecycleGrant($this->service, $this->world, ['value' => '500.00']);
    $ticket = issueTicket($this->world['student'], $this->world['year'], 200.00);
    $ticket->update(['status' => 'paid', 'paid_at' => now()]);
    applyTo($discount, $ticket, $this->world, $this->service);

    $this->service->revoke($discount->refresh(), 'توقف الصرف', $this->world['admin']);

    $discount->refresh();
    expect($discount->status)->toBe(DiscountStatus::Revoked)
        ->and($discount->usages()->count())->toBe(1)
        ->and((float) $ticket->refresh()->amount)->toBe(0.0);
});

// ── editability (R9) ───────────────────────────────────────────────────────

it('edits an untouched active discount and records old/new values', function () {
    $discount = lifecycleGrant($this->service, $this->world);

    $this->service->update($discount, [
        'scope' => DiscountScope::Registration->value,
        'mode' => DiscountMode::Fixed->value,
        'value' => '750.00',
        'reason' => 'تصحيح القيمة',
        'decision_number' => 'قرار-٢',
    ], $this->world['admin']);

    $discount->refresh();
    expect((float) $discount->value)->toBe(750.0)
        ->and((float) $discount->remaining_amount)->toBe(750.0)
        ->and($discount->reason)->toBe('تصحيح القيمة');

    $event = $discount->events()->where('action', DiscountEventAction::Edited->value)->firstOrFail();
    expect($event->meta['old']['value'])->toBe('500.00')
        ->and($event->meta['new']['value'])->toBe('750.00');
});

it('refuses to edit a discount that was already applied', function () {
    $discount = lifecycleGrant($this->service, $this->world);
    $ticket = issueTicket($this->world['student'], $this->world['year'], 200.00);
    applyTo($discount, $ticket, $this->world, $this->service);

    $this->service->update($discount->refresh(), ['value' => '999.00', 'mode' => DiscountMode::Fixed->value, 'scope' => DiscountScope::Registration->value, 'reason' => 'x'], $this->world['admin']);
})->throws(DiscountEditException::class, 'أعد منحه');

// ── explicit pending re-pricing (BR-7) ─────────────────────────────────────

it('re-prices a pending ticket on explicit request and logs the change', function () {
    $ticket = issueTicket($this->world['student'], $this->world['year'], 1200.00);
    $discount = lifecycleGrant($this->service, $this->world, ['value' => '500.00']);

    $this->service->applyToPendingTicket($discount, $ticket, $this->world['admin']);

    $ticket->refresh();
    expect((float) $ticket->original_amount)->toBe(1200.0)
        ->and((float) $ticket->discount_amount)->toBe(500.0)
        ->and((float) $ticket->amount)->toBe(700.0);

    expect($discount->events()->where('action', DiscountEventAction::Edited->value)->exists())->toBeTrue();
});

it('refuses to re-price a paid ticket', function () {
    $ticket = issueTicket($this->world['student'], $this->world['year'], 1200.00);
    $ticket->update(['status' => 'paid', 'paid_at' => now()]);
    $discount = lifecycleGrant($this->service, $this->world);

    $this->service->applyToPendingTicket($discount, $ticket->refresh(), $this->world['admin']);
})->throws(DiscountEditException::class);

it('grants after a pending ticket leave it untouched until the explicit action', function () {
    $ticket = issueTicket($this->world['student'], $this->world['year'], 1200.00);

    lifecycleGrant($this->service, $this->world);

    $ticket->refresh();
    expect($ticket->original_amount)->toBeNull()
        ->and((float) $ticket->amount)->toBe(1200.0)
        ->and($ticket->discountUsages()->count())->toBe(0);
});

// ── auto-revocation (FR-018) ───────────────────────────────────────────────

it('auto-revokes active discounts when the student is soft-deleted', function () {
    $discount = lifecycleGrant($this->service, $this->world);

    $this->world['student']->delete();

    $discount->refresh();
    expect($discount->status)->toBe(DiscountStatus::Revoked)
        ->and($discount->revoked_reason)->toBe('حذف الطالب')
        ->and($discount->events()->where('action', DiscountEventAction::AutoRevoked->value)->whereNull('user_id')->count())->toBe(1);
});

it('auto-revokes active discounts on transfer approval', function () {
    $world = $this->world;
    $discount = lifecycleGrant($this->service, $world, ['value' => '400.00']);
    $request = makeRequest($world);

    app(StudentTransferService::class)->approve($request, $world['admin']);

    expect($discount->refresh()->status)->toBe(DiscountStatus::Revoked)
        ->and($discount->revoked_reason)->toBe('تحويل الطالب');
});

it('keeps exhausted and revoked history untouched by auto-revocation', function () {
    $exhausted = lifecycleGrant($this->service, $this->world, ['value' => '200.00']);
    $ticket = issueTicket($this->world['student'], $this->world['year'], 200.00);
    applyTo($exhausted, $ticket, $this->world, $this->service);

    $this->world['student']->delete();

    expect($exhausted->refresh()->status)->toBe(DiscountStatus::Exhausted);
});

// ── revert on ticket deletion (invariants 2/3) ─────────────────────────────

it('restores discount balance when a discounted pending ticket is deleted', function () {
    $discount = lifecycleGrant($this->service, $this->world, ['value' => '300.00']);
    $ticket = issueTicket($this->world['student'], $this->world['year'], 300.00);
    applyTo($discount, $ticket, $this->world, $this->service);

    expect($discount->refresh()->status)->toBe(DiscountStatus::Exhausted);

    $this->service->revertTicket($ticket, $this->world['admin']);
    $ticket->delete();

    $discount->refresh();
    expect($discount->status)->toBe(DiscountStatus::Active)
        ->and((float) $discount->remaining_amount)->toBe(300.0)
        ->and($discount->usages()->count())->toBe(0)
        ->and($discount->events()->where('action', DiscountEventAction::Edited->value)->where('meta->source', 'ticket-reverted')->count())->toBe(1);
});

it('keeps a partially used discount consistent when only one of two tickets is deleted', function () {
    $discount = lifecycleGrant($this->service, $this->world, ['value' => '500.00']);
    $t1 = issueTicket($this->world['student'], $this->world['year'], 200.00);
    $t2 = issueTicket($this->world['student'], $this->world['year'], 400.00);
    applyTo($discount, $t1, $this->world, $this->service);
    applyTo($discount->refresh(), $t2, $this->world, $this->service);

    expect($discount->refresh()->status)->toBe(DiscountStatus::Exhausted);

    $this->service->revertTicket($t1, $this->world['admin']);
    $t1->delete();

    $discount->refresh();
    expect((float) $discount->remaining_amount)->toBe(200.0)
        ->and($discount->status)->toBe(DiscountStatus::PartiallyApplied)
        ->and((float) $discount->value - (float) $discount->usages()->sum('applied_amount') - (float) $discount->remaining_amount)->toBe(0.0);
});

it('leaves revoked discounts revoked while restoring their ledger on deletion', function () {
    $discount = lifecycleGrant($this->service, $this->world, ['value' => '500.00']);
    $ticket = issueTicket($this->world['student'], $this->world['year'], 200.00);
    applyTo($discount, $ticket, $this->world, $this->service);
    $this->service->revoke($discount->refresh(), 'إلغاء', $this->world['admin']);

    $this->service->revertTicket($ticket, $this->world['admin']);
    $ticket->delete();

    $discount->refresh();
    expect($discount->status)->toBe(DiscountStatus::Revoked)
        ->and((float) $discount->remaining_amount)->toBe(500.0);
});

it('FeeIssuance deleteTicket reverts applied discounts before destroying the row', function () {
    Permission::firstOrCreate(['name' => 'finance.delete', 'guard_name' => 'web']);
    $this->world['admin']->givePermissionTo('finance.delete');

    $discount = lifecycleGrant($this->service, $this->world, ['value' => '500.00']);
    $ticket = issueTicket($this->world['student'], $this->world['year'], 1200.00);
    applyTo($discount, $ticket, $this->world, $this->service);

    Livewire::actingAs($this->world['admin'])
        ->test(FeeIssuance::class)
        ->call('deleteTicket', $ticket->id);

    expect(StudentFeeTicket::find($ticket->id))->toBeNull()
        ->and($discount->refresh()->status)->toBe(DiscountStatus::Active)
        ->and((float) $discount->remaining_amount)->toBe(500.0);
});

// ── screen gates for edit/revoke/re-price ──────────────────────────────────

it('denies revoke, update and pending re-pricing without the right permission', function () {
    $this->world['admin']->givePermissionTo(['discounts.view']);
    $discount = lifecycleGrant($this->service, $this->world);

    Livewire::actingAs($this->world['admin'])->test(Index::class)
        ->call('revokeTarget', $discount->id)->assertStatus(403);

    Livewire::actingAs($this->world['admin'])->test(Index::class)
        ->call('updateDiscount')->assertStatus(403);

    Livewire::actingAs($this->world['admin'])->test(Index::class)
        ->call('applyToPendingTicket', $discount->id, 1)->assertStatus(403);

    expect($discount->refresh()->status)->toBe(DiscountStatus::Active);
});

it('surfaces a stale-page revoke as an error toast instead of a 500', function () {
    $this->world['admin']->givePermissionTo(['discounts.view', 'discounts.revoke']);
    $discount = lifecycleGrant($this->service, $this->world);
    $this->service->revoke($discount, 'سبقه مشرف آخر', $this->world['admin']);

    Livewire::actingAs($this->world['admin'])
        ->test(Index::class)
        ->call('revokeTarget', $discount->id)
        ->set('revokeForm.reason', 'محاولة متأخرة')
        ->call('revoke')
        ->assertDispatched('toast')
        ->assertSee('ملغى');

    expect($discount->refresh()->status)->toBe(DiscountStatus::Revoked)
        ->and($discount->revoked_reason)->toBe('سبقه مشرف آخر');
});

it('surfaces a stale-page edit as an error toast instead of a 500', function () {
    $this->world['admin']->givePermissionTo(['discounts.view', 'discounts.create', 'discounts.edit']);
    $discount = lifecycleGrant($this->service, $this->world);
    $ticket = issueTicket($this->world['student'], $this->world['year'], 200.00);
    applyTo($discount, $ticket, $this->world, $this->service);

    Livewire::actingAs($this->world['admin'])
        ->test(Index::class)
        ->set('editingId', $discount->id)
        ->set('form', [
            'student_id' => $this->world['student']->id,
            'scope' => DiscountScope::Registration->value,
            'fee_id' => null,
            'year_id' => null,
            'semester' => null,
            'mode' => DiscountMode::Fixed->value,
            'value' => '999',
            'reason' => 'تعديل متأخر',
            'decision_number' => null,
        ])
        ->call('updateDiscount')
        ->assertDispatched('toast');

    expect((float) $discount->refresh()->value)->toBe(500.0);
});

// ── re-pricing guards (T056) ───────────────────────────────────────────────

it('refuses to re-price a ticket whose fee the discount does not cover', function () {
    $ticket = issueTicket($this->world['student'], $this->world['year'], 1200.00);
    $discount = lifecycleGrant($this->service, $this->world, [
        'scope' => DiscountScope::Additional->value,
        'fee_id' => 42,
    ]);

    $this->service->applyToPendingTicket($discount, $ticket, $this->world['admin']);
})->throws(DiscountEditException::class);

it('refuses to re-price a ticket the discount already applies to', function () {
    $ticket = issueTicket($this->world['student'], $this->world['year'], 1200.00);
    $discount = lifecycleGrant($this->service, $this->world);

    $this->service->applyToPendingTicket($discount, $ticket, $this->world['admin']);
    $this->service->applyToPendingTicket($discount->refresh(), $ticket->refresh(), $this->world['admin']);
})->throws(DiscountEditException::class);

it('refuses to re-price with a revoked discount', function () {
    $ticket = issueTicket($this->world['student'], $this->world['year'], 1200.00);
    $discount = lifecycleGrant($this->service, $this->world);
    $this->service->revoke($discount, 'قرار', $this->world['admin']);

    $this->service->applyToPendingTicket($discount->refresh(), $ticket, $this->world['admin']);
})->throws(DiscountEditException::class, 'ملغى أو مستنفد');

// ── service integrity guards (T057) ────────────────────────────────────────

it('refuses to grant a non-positive value', function () {
    $this->service->grant([
        'student_id' => $this->world['student']->id,
        'scope' => DiscountScope::Registration->value,
        'mode' => DiscountMode::Fixed->value,
        'value' => '0',
        'reason' => 'سالبة',
    ], $this->world['admin']);
})->throws(\App\Exceptions\DiscountInvalidException::class, 'أكبر من صفر');

it('refuses to grant an out-of-bounds percentage', function () {
    $this->service->grant([
        'student_id' => $this->world['student']->id,
        'scope' => DiscountScope::Registration->value,
        'mode' => DiscountMode::Percentage->value,
        'value' => '150',
        'reason' => 'نسبة خاطئة',
    ], $this->world['admin']);
})->throws(\App\Exceptions\DiscountInvalidException::class, '100');

it('refuses to grant without a reason', function () {
    $this->service->grant([
        'student_id' => $this->world['student']->id,
        'scope' => DiscountScope::Registration->value,
        'mode' => DiscountMode::Fixed->value,
        'value' => '100',
        'reason' => '  ',
    ], $this->world['admin']);
})->throws(\App\Exceptions\DiscountInvalidException::class, 'إلزامي');

it('refuses to update to an invalid definition', function () {
    $discount = lifecycleGrant($this->service, $this->world);

    $this->service->update($discount, [
        'scope' => DiscountScope::Registration->value,
        'mode' => DiscountMode::Fixed->value,
        'value' => '-10',
        'reason' => 'سالب',
    ], $this->world['admin']);
})->throws(\App\Exceptions\DiscountInvalidException::class);
