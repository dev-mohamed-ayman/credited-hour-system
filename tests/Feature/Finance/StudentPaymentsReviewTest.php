<?php

use App\Enums\Semester;
use App\Livewire\Admin\Finance\StudentPaymentsReview;
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

    // A: 3000 study pending (not paid) + 1000 other paid => partial, study unpaid, other paid.
    $this->studentA = reviewStudent($this->world, 'طالب علي', 'SVA001', studyStatus: 'freshman');
    reviewTicket($this->studentA, $this->world['year'], 3000, 'pending', 'registration');
    reviewTicket($this->studentA, $this->world['year'], 1000, 'paid', 'additional');

    // B: 5000 study paid fully + 700 other pending.
    $this->studentB = reviewStudent($this->world, 'طالب بكري', 'SVB002', studyStatus: 'remaining');
    reviewTicket($this->studentB, $this->world['year'], 5000, 'paid', 'registration');
    reviewTicket($this->studentB, $this->world['year'], 700, 'pending', 'military_education');

    // C: only a cancelled ticket => must not appear at all.
    $this->studentC = reviewStudent($this->world, 'طالب مثلث', 'SVC003');
    reviewTicket($this->studentC, $this->world['year'], 9999, 'cancelled');

    // D: no tickets at all.
    $this->studentD = reviewStudent($this->world, 'طالب رباعي', 'SVD004');
});

function reviewStudent(array $world, string $name, string $username, ?string $studyStatus = null): Student
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
        'study_status' => $studyStatus,
    ]);
}

function reviewTicket(Student $student, Year $year, float $amount, string $status, string $feeType = 'registration'): StudentFeeTicket
{
    return StudentFeeTicket::create([
        'ticket_number' => 'SV'.uniqid().mt_rand(100, 999),
        'student_id' => $student->id,
        'fee_type' => $feeType,
        'fee_id' => 1,
        'fee_name' => 'مصاريف مراجعة',
        'amount' => $amount,
        'original_amount' => $amount,
        'discount_amount' => 0,
        'status' => $status,
        'year_id' => $year->id,
        'semester' => Semester::FIRST->value,
        'paid_at' => $status === 'paid' ? now() : null,
    ]);
}

function reviewScreen(): \Livewire\Features\SupportTesting\Testable
{
    return Livewire::actingAs(test()->world['admin'])->test(StudentPaymentsReview::class);
}

it('splits study vs other payments per student and hides ticketless students', function () {
    $screen = reviewScreen();

    $screen->assertSee('طالب علي')
        ->assertSee('طالب بكري')
        ->assertDontSee('طالب مثلث')
        ->assertDontSee('طالب رباعي');

    $row = $screen->instance()
        ->reviewQuery()
        ->get()
        ->firstWhere('username', 'SVA001');

    $component = $screen->instance();

    expect((float) $row->study_gross)->toBe(3000.0)
        ->and((float) $row->study_paid)->toBe(0.0)
        ->and($component->remainingOf($row, 'study'))->toBe(3000.0)
        ->and((float) $row->other_paid)->toBe(1000.0)
        ->and($component->remainingOf($row, 'other'))->toBe(0.0)
        ->and($component->paymentStatusLabel($row))->toBe('مسدد جزئياً');
});

it('filters by study payments remaining status', function () {
    $screen = reviewScreen();

    $screen->set('studyRemaining', 'unpaid')
        ->assertSee('طالب علي')
        ->assertDontSee('طالب بكري');

    $screen->set('studyRemaining', 'paid')
        ->assertSee('طالب بكري')
        ->assertDontSee('طالب علي');
});

it('filters by other payments remaining status', function () {
    reviewScreen()
        ->set('otherRemaining', 'unpaid')
        ->assertSee('طالب بكري')
        ->assertDontSee('طالب علي');
});

it('filters by student classification', function () {
    $screen = reviewScreen();

    $screen->set('searchStudyStatus', 'freshman')
        ->assertSee('طالب علي')
        ->assertDontSee('طالب بكري');

    $screen->set('searchStudyStatus', 'remaining')
        ->assertSee('طالب بكري')
        ->assertDontSee('طالب علي');
});

it('searches students by code', function () {
    reviewScreen()
        ->set('searchStudent', 'SVB002')
        ->assertSee('طالب بكري')
        ->assertDontSee('طالب علي');
});

it('scopes tickets to the default current year and semester', function () {
    $oldYear = Year::create(['year' => '2020-2021']);
    reviewTicket($this->studentD, $oldYear, 1500, 'pending');

    // Default filters = current year/semester => student D is out of scope.
    reviewScreen()
        ->assertDontSee('طالب رباعي');

    // Clearing the year filter surfaces D with its old-year ticket.
    $screen = reviewScreen()->set('searchYear', '')->set('searchSemester', '');

    $screen->assertSee('طالب رباعي');
});

it('counts approved registered course hours', function () {
    fundWallet($this->world['student'], 2000, $this->world['year']);
    chargedRegistration($this->world, courseCount: 2);
    reviewTicket($this->world['student'], $this->world['year'], 2000, 'paid');

    $row = reviewScreen()->instance()
        ->reviewQuery()
        ->get()
        ->firstWhere('username', 'CS250001');

    expect((int) $row->registered_hours)->toBe(6);
});

it('exports the review as csv with split columns', function () {
    $response = reviewScreen()->instance()->exportCsv();

    ob_start();
    $response->sendContent();
    $csv = ob_get_clean();

    expect($csv)->toContain('باقي المصاريف الدراسية')
        ->toContain('طالب علي')
        ->toContain('طالب بكري')
        ->not->toContain('طالب مثلث')
        ->toContain('مسدد جزئياً');
});

it('blocks admins without finance permission', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(StudentPaymentsReview::class)
        ->assertStatus(403);
});
