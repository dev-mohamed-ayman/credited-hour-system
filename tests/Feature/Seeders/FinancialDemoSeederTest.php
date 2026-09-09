<?php

use App\Livewire\Admin\Finance\DailyPayments;
use App\Livewire\Admin\Finance\StudentPaymentsReview;
use App\Livewire\Admin\Finance\WalletsReport;
use App\Models\DailyPaymentDateTime;
use App\Models\Student;
use App\Models\StudentFeeTicket;
use App\Models\StudentWallet;
use App\Models\User;
use App\Models\WalletTransaction;
use Database\Seeders\FinancialDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(FinancialDemoSeeder::class);
    $this->admin = User::where('is_super_admin', true)->firstOrFail();
});

function financeAdmin(): User
{
    return test()->admin;
}

function snapshotCounts(): array
{
    return [
        'students' => Student::count(),
        'tickets' => StudentFeeTicket::count(),
        'wallets' => StudentWallet::count(),
        'movements' => WalletTransaction::count(),
        'sum_paid' => (float) StudentFeeTicket::where('status', 'paid')->sum('amount'),
        'balance' => (float) StudentWallet::sum('balance'),
    ];
}

it('seeds each finance scenario with the exact expected figures', function () {
    $scenario = fn (string $username) => Student::with('wallet')->where('username', $username)->firstOrFail();

    $ahmed = $scenario('FS250101');
    expect(StudentFeeTicket::where('student_id', $ahmed->id)->where('status', 'pending')->count())->toBe(0)
        ->and((float) $ahmed->wallet->balance)->toBe(2700.0); // 3100+150+300 deposits - 850 charged registration

    $sara = $scenario('FS250102');
    $discounted = StudentFeeTicket::where('student_id', $sara->id)->where('fee_type', 'registration')->firstOrFail();
    expect((float) $discounted->original_amount)->toBe(3100.0)
        ->and((float) $discounted->discount_amount)->toBe(600.0)
        ->and((float) $discounted->amount)->toBe(2500.0)
        ->and(StudentFeeTicket::where('student_id', $sara->id)->where('status', 'pending')->count())->toBe(1)
        ->and((float) $sara->wallet->balance)->toBe(1650.0); // 2500 deposit - 850 charged registration

    $mohamed = $scenario('FS250103');
    expect(StudentFeeTicket::where('student_id', $mohamed->id)->where('status', 'pending')->count())->toBe(3)
        ->and(StudentFeeTicket::where('student_id', $mohamed->id)->where('status', 'paid')->count())->toBe(0)
        ->and((float) $mohamed->wallet->balance)->toBe(300.0); // refund only

    $leila = $scenario('FS250104');
    $zero = StudentFeeTicket::where('student_id', $leila->id)->where('amount', 0)->firstOrFail();
    expect($zero->status)->toBe('paid')
        ->and((float) $zero->original_amount)->toBe(3100.0)
        ->and(WalletTransaction::where('reference_id', $zero->id)->where('type', 'deposit')->count())->toBe(0);

    $khaled = $scenario('FS250105');
    expect(StudentFeeTicket::where('student_id', $khaled->id)->where('status', 'cancelled')->count())->toBe(1)
        ->and((float) StudentFeeTicket::where('student_id', $khaled->id)->where('status', 'paid')->sum('amount'))->toBe(7600.0);
});

it('is idempotent and safe to re-run', function () {
    $before = snapshotCounts();

    $this->seed(FinancialDemoSeeder::class);

    expect(snapshotCounts())->toBe($before);
});

it('lets the wallets report answer every amount/status scenario', function () {
    $screen = fn () => Livewire::actingAs(financeAdmin())->test(WalletsReport::class);

    $screen()->set('paymentStatus', 'paid')
        ->assertSee('أحمد كامل السيد')
        ->assertDontSee('محمد إبراهيم');

    $screen()->set('paymentStatus', 'partial')
        ->assertSee('سارة محمود')
        ->assertSee('خالد سمير');

    $screen()->set('paymentStatus', 'unpaid')
        ->assertSee('محمد إبراهيم')
        ->assertDontSee('أحمد كامل السيد');

    // Paid between 7000 and 8000 => only the big payer; the cancelled 9999 never leaks in.
    $screen()->set('paidFrom', '7000')->set('paidTo', '8000')
        ->assertSee('خالد سمير')
        ->assertDontSee('أحمد كامل السيد');

    // Due between 3000 and 3500 => the never-paid student (3100+200+200 pending).
    $screen()->set('dueFrom', '3000')->set('dueTo', '3500')
        ->assertSee('محمد إبراهيم')
        ->assertDontSee('خالد سمير');

    $screen()->set('searchFeeType', 'military_education')
        ->assertSee('أحمد كامل السيد')
        ->assertSee('خالد سمير')
        ->assertDontSee('سارة محمود');
});

it('lets the students review show the discounted study split', function () {
    $rows = Livewire::actingAs(financeAdmin())
        ->test(StudentPaymentsReview::class)
        ->instance()
        ->reviewQuery()
        ->get();

    $sara = $rows->firstWhere('username', 'FS250102');
    expect((float) $sara->study_gross)->toBe(3100.0)
        ->and((float) $sara->study_discount)->toBe(600.0)
        ->and((float) $sara->study_paid)->toBe(2500.0)
        ->and((float) $sara->study_remaining)->toBe(0.0)
        ->and((float) $sara->other_remaining)->toBe(1000.0);

    $ahmed = $rows->firstWhere('username', 'FS250101');
    expect((int) $ahmed->registered_hours)->toBe(3);

    Livewire::actingAs(financeAdmin())
        ->test(StudentPaymentsReview::class)
        ->set('studyRemaining', 'unpaid')
        ->assertSee('محمد إبراهيم')
        ->assertDontSee('سارة محمود');
});

it('scopes reviews by semester and department', function () {
    Livewire::actingAs(financeAdmin())
        ->test(StudentPaymentsReview::class)
        ->set('searchSemester', 'second')
        ->assertSee('تامر سعيد')
        ->assertDontSee('سارة محمود');

    Livewire::actingAs(financeAdmin())
        ->test(StudentPaymentsReview::class)
        ->set('searchDepartment', \App\Models\Department::where('code', 'ACC')->value('id'))
        ->assertSee('هند رامي')
        ->assertDontSee('خالد سمير');
});

it('reviews each treasury day with methods, receipts and cashiers', function () {
    $day3 = DailyPaymentDateTime::whereDate('date', now()->subDays(3)->toDateString())->firstOrFail();

    $screen = Livewire::actingAs(financeAdmin())
        ->test(DailyPayments::class)
        ->set('selectedDate', $day3->date->toDateString());

    // Day 3 held: 3100 cash + 150 visa + 300 cash = 3400 cash / 150 credit, 3 payments.
    $screen->assertSee('3,400.00')
        ->assertSee('FIN101-REG')
        ->assertSee('مدير النظام')
        ->assertDontSee('FIN102-REG');

    // Day 1 held: the zero-net full-discount ticket, the 150 cash fee, and the visa registration.
    $screen->set('selectedDate', now()->subDays(1)->toDateString())
        ->assertSee('FIN104-REG')
        ->assertSee('FIN105-REG')
        ->assertDontSee('FIN101-REG');
});

it('renders the open treasury day and the range mode over the seeded flows', function () {
    $open = Livewire::actingAs(financeAdmin())
        ->test(DailyPayments::class)
        ->set('selectedDate', now()->toDateString())
        ->assertSee('FIN105-LAB')
        ->assertSee('استرداد رسوم كتاب ملغي')
        ->assertDontSee('FIN105-CANCELLED');
    $open->assertOk();

    Livewire::actingAs(financeAdmin())
        ->test(DailyPayments::class)
        ->set('reviewMode', 'period')
        ->set('rangeFrom', now()->subDays(4)->format('Y-m-d\TH:i'))
        ->set('rangeTo', now()->format('Y-m-d\TH:i'))
        ->assertSee('FIN101-REG')
        ->assertSee('FIN108-REG');
});
