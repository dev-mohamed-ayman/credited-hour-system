<?php

use App\Enums\DiscountMode;
use App\Enums\DiscountScope;
use App\Livewire\Admin\Finance\DailyPayments;
use App\Livewire\Admin\Finance\Discounts\Index;
use App\Livewire\Admin\Finance\StudentFinancialStatus;
use App\Models\Setting;
use App\Services\DiscountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->world = billingWorld();
    $this->service = app(DiscountService::class);

    foreach (['discounts.view', 'discounts.create', 'finance.view', 'finance.edit'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
    $this->world['admin']->givePermissionTo(['discounts.view', 'discounts.create', 'finance.view', 'finance.edit']);
});

function reportingFixture($world, DiscountService $service): array
{
    $discount = $service->grant([
        'student_id' => $world['student']->id,
        'scope' => DiscountScope::Registration->value,
        'mode' => DiscountMode::Fixed->value,
        'value' => '500.00',
        'reason' => 'منحة تفوق',
        'decision_number' => 'قرار-٩',
    ], $world['admin']);

    $ticket = issueTicket($world['student'], $world['year'], 1200.00);
    $plan = $service->planApplication(collect([$discount->refresh()]), '1200.00');
    $service->applyToTicket($ticket, $plan['applied'], $world['admin']);

    return [$discount->refresh(), $ticket->refresh()];
}

it('produces a discounts report whose totals reconcile with ticket snapshots', function () {
    [$discount, $ticket] = reportingFixture($this->world, $this->service);

    Livewire::actingAs($this->world['admin'])
        ->test(Index::class)
        ->call('toggleReport')
        ->assertSee('منحة تفوق')
        ->assertSee('قرار-٩')
        ->assertSee('500.00')
        ->assertSee('إجمالي الخصومات المطبَّقة');

    $total = (float) \App\Models\StudentDiscountUsage::sum('applied_amount');
    expect($total)->toBe((float) $ticket->discount_amount);
});

it('filters the report by student search', function () {
    [$discount, $ticket] = reportingFixture($this->world, $this->service);

    Livewire::actingAs($this->world['admin'])
        ->test(Index::class)
        ->call('toggleReport')
        ->set('reportFilters.student_code', 'NONEXIST999')
        ->assertSee('لا توجد نتائج');
});

it('shows original dues, total discounts, paid and remaining on the financial statement', function () {
    [$discount, $ticket] = reportingFixture($this->world, $this->service);

    Livewire::actingAs($this->world['admin'])
        ->test(StudentFinancialStatus::class)
        ->set('searchQuery', $this->world['student']->username)
        ->call('searchStudent')
        ->assertSee('إجمالي الخصومات')
        ->assertSee(number_format(500, 2))
        ->assertSee(number_format(1200, 2));
});

it('surfaces the discount line in daily payments for a day with payments', function () {
    Setting::query()->firstOrCreate([])->update(['ministerial_receipt_current' => 100, 'ministerial_receipt_end' => 110]);
    \Illuminate\Support\Facades\DB::table('daily_payments_datetime')->insert([
        'date' => now()->toDateString(),
        'start_date' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    [$discount, $ticket] = reportingFixture($this->world, $this->service);
    $ticket->update(['status' => 'paid', 'paid_at' => now()]);

    Livewire::actingAs($this->world['admin'])
        ->test(DailyPayments::class)
        ->assertSee('خصم')
        ->assertSee(number_format(500.00, 2));
});

it('merges audit events and applications into one discount timeline', function () {
    $discount = $this->service->grant([
        'student_id' => $this->world['student']->id,
        'scope' => DiscountScope::Registration->value,
        'mode' => DiscountMode::Fixed->value,
        'value' => '800.00',
        'reason' => 'منحة تفوق',
        'decision_number' => 'قرار-٩',
    ], $this->world['admin']);
    $ticket = issueTicket($this->world['student'], $this->world['year'], 1200.00);
    $plan = $this->service->planApplication(collect([$discount->refresh()]), '300.00');
    $this->service->applyToTicket($ticket, $plan['applied'], $this->world['admin']);

    $this->service->revoke($discount->refresh(), 'قرار إلغاء تجريبي', $this->world['admin']);

    Livewire::actingAs($this->world['admin'])
        ->test(Index::class)
        ->call('showHistory', $discount->id)
        ->assertSee('منح')
        ->assertSee('قرار إلغاء تجريبي')
        ->assertSee('تطبيق على حافظة');
});
