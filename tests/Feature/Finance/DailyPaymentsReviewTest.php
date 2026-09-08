<?php

use App\Enums\Semester;
use App\Livewire\Admin\Finance\DailyPayments;
use App\Models\DailyPaymentDateTime;
use App\Models\StudentFeeTicket;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['finance.view', 'finance.edit'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }

    $this->world = billingWorld();
    $this->world['admin']->givePermissionTo(['finance.view', 'finance.edit']);
});

function paidReviewTicket(array $world, float $amount, string $method, string $feeType = 'registration', ?\Carbon\Carbon $paidAt = null): StudentFeeTicket
{
    $ticket = StudentFeeTicket::create([
        'ticket_number' => 'DP'.uniqid().mt_rand(100, 999),
        'student_id' => $world['student']->id,
        'fee_type' => $feeType,
        'fee_id' => 1,
        'fee_name' => 'مصاريف مراجعة يومية',
        'amount' => $amount,
        'original_amount' => $amount,
        'discount_amount' => 0,
        'status' => 'paid',
        'payment_method' => $method,
        'paid_at' => $paidAt ?? now(),
        'year_id' => $world['year']->id,
        'semester' => Semester::FIRST->value,
    ]);

    app(WalletService::class)->deposit(
        student: $world['student'],
        amount: $amount,
        yearId: $world['year']->id,
        semester: Semester::FIRST,
        reason: 'إيداع مبلغ مالي من سداد حافظة',
        reference: $ticket,
        performedBy: $world['admin'],
    );

    return $ticket;
}

it('reviews a daily window with breakdowns, cashiers and wallet movements', function () {
    DailyPaymentDateTime::create(['date' => now()->toDateString(), 'start_date' => now()->subHours(2)]);

    $ticket = paidReviewTicket($this->world, 1500, 'cash');
    paidReviewTicket($this->world, 800, 'credit', 'additional');

    Livewire::actingAs($this->world['admin'])
        ->test(DailyPayments::class)
        ->assertSee('مراجعة اليوميات')
        ->assertSee($ticket->ticket_number)
        ->assertSee('2,300.00')                       // الإجمالي المحصل
        ->assertSee('نقدي (كاش)')                      // طريقة الدفع
        ->assertSee('مصاريف مراجعة يومية')              // بيانات المدفوعات
        ->assertSee($this->world['admin']->name)       // المحصل + تحركات المحفظة
        ->assertSee('إيداع مبلغ مالي من سداد حافظة')
        ->assertSee('رسوم إضافية');
});

it('supports an explicit datetime-range review mode', function () {
    paidReviewTicket($this->world, 1000, 'cash', paidAt: now()->subDays(3));
    $outside = paidReviewTicket($this->world, 4000, 'cash', paidAt: now()->subDays(10));

    Livewire::actingAs($this->world['admin'])
        ->test(DailyPayments::class)
        ->set('reviewMode', 'period')
        ->set('rangeFrom', now()->subDays(5)->format('Y-m-d\TH:i'))
        ->set('rangeTo', now()->addDay()->format('Y-m-d\TH:i'))
        ->assertSee('1,000.00')
        ->assertDontSee($outside->ticket_number);
});

it('guards an invalid range without rendering a review', function () {
    Livewire::actingAs($this->world['admin'])
        ->test(DailyPayments::class)
        ->set('reviewMode', 'period')
        ->set('rangeFrom', now()->subDay()->format('Y-m-d\TH:i'))
        ->set('rangeTo', now()->subDays(2)->format('Y-m-d\TH:i'))
        ->assertSee('النهاية ليست قبل البداية');
});

it('exports the payments of the active window as csv', function () {
    DailyPaymentDateTime::create(['date' => now()->toDateString(), 'start_date' => now()->subHours(2)]);
    $ticket = paidReviewTicket($this->world, 1250, 'cash');

    $response = Livewire::actingAs($this->world['admin'])
        ->test(DailyPayments::class)
        ->instance()
        ->exportCsv();

    ob_start();
    $response->sendContent();
    $csv = ob_get_clean();

    expect($csv)->toContain($ticket->ticket_number)
        ->toContain('أمين الصندوق')
        ->toContain($this->world['admin']->name)
        ->toContain('1250.00');
});

it('opens and closes the treasury day', function () {
    $component = Livewire::actingAs($this->world['admin'])->test(DailyPayments::class);

    $component->set('selectedDate', now()->addDay()->toDateString())->call('openDay');

    expect(DailyPaymentDateTime::whereDate('date', now()->addDay()->toDateString())->exists())->toBeTrue();

    $component->call('closeDay');

    expect(DailyPaymentDateTime::whereNull('end_date')->count())->toBe(0);
});

it('blocks admins without finance permission', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(DailyPayments::class)
        ->assertStatus(403);
});
