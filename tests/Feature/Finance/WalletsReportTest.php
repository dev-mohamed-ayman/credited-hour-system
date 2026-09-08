<?php

use App\Enums\Semester;
use App\Livewire\Admin\Finance\WalletsReport;
use App\Models\Student;
use App\Models\StudentFeeTicket;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'finance.view', 'guard_name' => 'web']);

    $this->world = billingWorld();
    $this->world['admin']->givePermissionTo('finance.view');

    $this->paidFull = makeStudent($this->world, 'طالب مسدد بالكامل', 'RPY001');
    $this->neverPaid = makeStudent($this->world, 'طالب غير مسدد', 'RDEBT01');
    $this->partial = makeStudent($this->world, 'طالب مسدد جزئياً', 'RPART01');
    $this->ticketless = makeStudent($this->world, 'طالب بلا حافظات', 'RNONE01');

    // طالب تجريبي: due 5000 registration + paid 2000 additional => paid 2000, due 5000.
    reportTicket($this->world['student'], $this->world['year'], 5000, 'pending');
    reportTicket($this->world['student'], $this->world['year'], 2000, 'paid', 'additional');

    reportTicket($this->paidFull, $this->world['year'], 6000, 'paid');
    reportTicket($this->neverPaid, $this->world['year'], 3000, 'pending');
    reportTicket($this->neverPaid, $this->world['year'], 500, 'cancelled');

    reportTicket($this->partial, $this->world['year'], 4000, 'paid');
    reportTicket($this->partial, $this->world['year'], 1000, 'pending');
});

function makeStudent(array $world, string $name, string $username): Student
{
    return Student::create([
        'name' => $name,
        'certificate_type_id' => $world['student']->certificate_type_id,
        'national_id' => fake()->unique()->numerify('##############'),
        'username' => $username,
        'password' => bcrypt('password'),
        'plain_password' => 'password',
        'section_id' => $world['section']->id,
        'level_id' => $world['level']->id,
        'year_id' => $world['year']->id,
        'semester' => Semester::FIRST->value,
    ]);
}

function reportTicket(Student $student, Year $year, float $amount, string $status, string $feeType = 'registration'): StudentFeeTicket
{
    return StudentFeeTicket::create([
        'ticket_number' => 'WR'.uniqid().mt_rand(100, 999),
        'student_id' => $student->id,
        'fee_type' => $feeType,
        'fee_id' => 1,
        'fee_name' => 'مصاريف '.($feeType === 'additional' ? 'إضافية' : 'تسجيل'),
        'amount' => $amount,
        'original_amount' => $amount,
        'discount_amount' => 0,
        'status' => $status,
        'year_id' => $year->id,
        'semester' => Semester::FIRST->value,
        'paid_at' => $status === 'paid' ? now() : null,
    ]);
}

function reportScreen(): \Livewire\Features\SupportTesting\Testable
{
    return Livewire::actingAs(test()->world['admin'])->test(WalletsReport::class);
}

it('lists only students that have non-cancelled tickets', function () {
    reportScreen()
        ->assertSee('طالب مسدد بالكامل')
        ->assertSee('طالب غير مسدد')
        ->assertSee('طالب مسدد جزئياً')
        ->assertDontSee('طالب بلا حافظات');
});

it('filters students by payment status', function () {
    $component = reportScreen();

    $component->set('paymentStatus', 'paid')
        ->assertSee('طالب مسدد بالكامل')
        ->assertDontSee('طالب غير مسدد')
        ->assertDontSee('طالب مسدد جزئياً');

    $component->set('paymentStatus', 'unpaid')
        ->assertSee('طالب غير مسدد')
        ->assertDontSee('طالب مسدد بالكامل');

    $component->set('paymentStatus', 'partial')
        ->assertSee('طالب مسدد جزئياً')
        ->assertSee('طالب تجريبي')
        ->assertDontSee('طالب مسدد بالكامل');
});

it('filters students by paid amount range including exact values', function () {
    $component = reportScreen();

    $component->set('paidFrom', '2000')->set('paidTo', '2000')
        ->assertSee('طالب تجريبي')
        ->assertDontSee('طالب مسدد بالكامل')
        ->assertDontSee('طالب مسدد جزئياً');

    $component->set('paidFrom', '5000')->set('paidTo', '6000')
        ->assertSee('طالب مسدد بالكامل')
        ->assertDontSee('طالب تجريبي')
        ->assertDontSee('طالب مسدد جزئياً')
        ->assertDontSee('طالب غير مسدد');

    $component->set('paidFrom', '5001')
        ->assertSee('طالب مسدد بالكامل')
        ->assertDontSee('طالب تجريبي');
});

it('filters students by remaining due amount range', function () {
    $component = reportScreen();

    $component->set('dueFrom', '5000')
        ->assertSee('طالب تجريبي')
        ->assertDontSee('طالب مسدد بالكامل');

    $component->set('dueFrom', '1000')->set('dueTo', '1000')
        ->assertSee('طالب مسدد جزئياً')
        ->assertDontSee('طالب تجريبي');

    // Due of the unpaid student is 3000, not 3500: the cancelled ticket must be ignored.
    $component->set('dueFrom', '')->set('dueTo', '3000')
        ->assertSee('طالب غير مسدد');

    $component->set('dueTo', '2999')
        ->assertDontSee('طالب غير مسدد');
});

it('scopes the report to a single fee type', function () {
    reportScreen()
        ->set('searchFeeType', 'additional')
        ->assertSee('طالب تجريبي')
        ->assertDontSee('طالب مسدد بالكامل')
        ->assertDontSee('طالب غير مسدد');
});

it('searches students by name or code', function () {
    reportScreen()
        ->set('searchStudent', 'RDEBT01')
        ->assertSee('طالب غير مسدد')
        ->assertDontSee('طالب تجريبي');
});

it('shows ticket details when a row is expanded', function () {
    $ticket = StudentFeeTicket::query()
        ->where('student_id', $this->partial->id)
        ->where('status', 'pending')
        ->firstOrFail();

    reportScreen()
        ->call('toggleDetails', $this->partial->id)
        ->assertSee($ticket->ticket_number)
        ->assertSee('تفاصيل الحافظات', false)
        ->call('toggleDetails', $this->partial->id)
        ->assertDontSee($ticket->ticket_number);
});

it('scopes ticket aggregates to the selected year', function () {
    $otherYear = Year::create(['year' => '2024-2025']);
    reportTicket($this->ticketless, $otherYear, 700, 'pending');

    $component = reportScreen();

    $component->set('searchYear', $otherYear->id)
        ->assertSee('طالب بلا حافظات')
        ->assertDontSee('طالب تجريبي')
        ->assertDontSee('طالب مسدد بالكامل');

    $component->set('searchYear', '')
        ->assertSee('طالب بلا حافظات')
        ->assertSee('طالب تجريبي')
        ->assertSee('طالب مسدد بالكامل');
});

it('exports the filtered report as a csv download', function () {
    $response = reportScreen()
        ->set('paymentStatus', 'unpaid')
        ->instance()
        ->exportCsv();

    expect($response->headers->get('Content-Type'))->toContain('text/csv')
        ->and($response->headers->get('Content-Disposition'))->toContain('wallets-report-');

    ob_start();
    $response->sendContent();
    $csv = ob_get_clean();

    expect($csv)->toContain('طالب غير مسدد')
        ->not->toContain('طالب مسدد بالكامل')
        ->toContain('حالة السداد');
});

it('blocks admins without finance permission', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(WalletsReport::class)
        ->assertStatus(403);
});
