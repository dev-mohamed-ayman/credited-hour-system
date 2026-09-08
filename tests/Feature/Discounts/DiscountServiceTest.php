<?php

use App\Enums\DiscountEventAction;
use App\Enums\DiscountMode;
use App\Enums\DiscountScope;
use App\Enums\DiscountStatus;
use App\Enums\Semester;
use App\Models\Student;
use App\Models\StudentDiscount;
use App\Models\User;
use App\Services\DiscountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->world = billingWorld();
    $this->service = app(DiscountService::class);
});

/**
 * @param  array<string, mixed>  $overrides
 */
function grantDiscount(DiscountService $service, Student $student, User $actor, array $overrides = []): StudentDiscount
{
    return $service->grant(array_merge([
        'student_id' => $student->id,
        'scope' => DiscountScope::Registration,
        'mode' => DiscountMode::Fixed,
        'value' => '500.00',
        'reason' => 'شهادة تكريم',
        'decision_number' => 'قرار-١',
    ], $overrides), $actor);
}

function ageDiscount(StudentDiscount $discount, string $modifier): StudentDiscount
{
    $discount->forceFill(['created_at' => strtotime($modifier) ? date('Y-m-d H:i:s', strtotime($modifier)) : now()])->save();

    return $discount->refresh();
}

// ── grant ──────────────────────────────────────────────────────────────────

it('grants a fixed discount immediately active with remaining initialized to value', function () {
    $discount = grantDiscount($this->service, $this->world['student'], $this->world['admin']);

    expect($discount->status)->toBe(DiscountStatus::Active)
        ->and((float) $discount->remaining_amount)->toBe(500.0)
        ->and($discount->created_by)->toBe($this->world['admin']->id)
        ->and($discount->usages)->toBeEmpty();

    $event = $discount->events()->first();
    expect($event->action)->toBe(DiscountEventAction::Granted)
        ->and($event->user_id)->toBe($this->world['admin']->id);
});

it('grants a percentage discount with no remaining balance', function () {
    $discount = grantDiscount($this->service, $this->world['student'], $this->world['admin'], [
        'mode' => DiscountMode::Percentage,
        'value' => '50.00',
    ]);

    expect($discount->remaining_amount)->toBeNull()
        ->and($discount->status)->toBe(DiscountStatus::Active);
});

// ── eligibleFor ────────────────────────────────────────────────────────────

it('detects eligible discounts by fee category, year and semester', function () {
    $student = $this->world['student'];
    $year = $this->world['year'];

    $otherYearModel = \App\Models\Year::create([
        'year' => '2026-2027',
        'first_semester_status' => \App\Enums\SemesterStatus::DISABLED,
        'second_semester_status' => \App\Enums\SemesterStatus::DISABLED,
        'summer_semester_status' => \App\Enums\SemesterStatus::DISABLED,
    ]);

    $matched = grantDiscount($this->service, $student, $this->world['admin'], ['year_id' => $year->id, 'semester' => Semester::FIRST]);
    $otherFee = grantDiscount($this->service, $student, $this->world['admin'], ['scope' => DiscountScope::Additional, 'fee_id' => 7]);
    $otherYear = grantDiscount($this->service, $student, $this->world['admin'], ['year_id' => $otherYearModel->id]);
    $otherSemester = grantDiscount($this->service, $student, $this->world['admin'], ['semester' => Semester::SECOND]);
    $any = grantDiscount($this->service, $student, $this->world['admin'], ['scope' => DiscountScope::Any]);

    $eligible = $this->service->eligibleFor($student, 'registration', null, $year->id, Semester::FIRST);

    $ids = $eligible->pluck('id')->all();
    expect($ids)->toContain($matched->id)
        ->and($ids)->toContain($any->id)
        ->and($ids)->not->toContain($otherFee->id)
        ->and($ids)->not->toContain($otherYear->id)
        ->and($ids)->not->toContain($otherSemester->id);
});

it('excludes revoked and fully exhausted discounts but keeps partially applied ones', function () {
    $student = $this->world['student'];

    $active = grantDiscount($this->service, $student, $this->world['admin']);
    $revoked = grantDiscount($this->service, $student, $this->world['admin']);
    $partially = grantDiscount($this->service, $student, $this->world['admin']);
    $exhausted = grantDiscount($this->service, $student, $this->world['admin']);

    $revoked->forceFill(['status' => DiscountStatus::Revoked])->save();
    $partially->forceFill(['status' => DiscountStatus::PartiallyApplied])->save();
    $exhausted->forceFill(['status' => DiscountStatus::Exhausted, 'remaining_amount' => 0])->save();

    $eligible = $this->service->eligibleFor($student, 'registration', null, $this->world['year']->id, Semester::FIRST);

    expect($eligible->pluck('id')->all())
        ->toBe([$active->id, $partially->id]);
});

it('matches a specific additional fee by id but not others', function () {
    $student = $this->world['student'];

    $card = grantDiscount($this->service, $student, $this->world['admin'], ['scope' => DiscountScope::Additional, 'fee_id' => 7]);
    grantDiscount($this->service, $student, $this->world['admin'], ['scope' => DiscountScope::Additional, 'fee_id' => 8]);

    $forCard7 = $this->service->eligibleFor($student, 'additional', 7, $this->world['year']->id, Semester::FIRST);
    $forCard9 = $this->service->eligibleFor($student, 'additional', 9, $this->world['year']->id, Semester::FIRST);

    expect($forCard7->pluck('id')->all())->toBe([$card->id])
        ->and($forCard9)->toBeEmpty();
});

// ── planApplication (pure allocation) ──────────────────────────────────────

it('plans full consumption of a fixed discount larger than nothing on a bigger ticket', function () {
    $discount = grantDiscount($this->service, $this->world['student'], $this->world['admin']);

    $plan = $this->service->planApplication(collect([$discount]), '1200.00');

    expect($plan['applied'])->toBe([$discount->id => '500.00'])
        ->and($plan['net'])->toBe('700.00');
});

it('plans partial application capped at the ticket original amount', function () {
    $discount = grantDiscount($this->service, $this->world['student'], $this->world['admin']);

    $plan = $this->service->planApplication(collect([$discount]), '200.00');

    expect($plan['applied'])->toBe([$discount->id => '200.00'])
        ->and($plan['net'])->toBe('0.00');
});

it('caps total discount at the original with oldest-first ordering', function () {
    $older = ageDiscount(grantDiscount($this->service, $this->world['student'], $this->world['admin'], ['value' => '400.00']), '-2 days');
    $newer = ageDiscount(grantDiscount($this->service, $this->world['student'], $this->world['admin'], ['value' => '300.00']), '-1 days');

    $plan = $this->service->planApplication(collect([$newer, $older])->sortBy(fn ($d) => $d->created_at), '500.00');

    expect(array_keys($plan['applied']))->toBe([$older->id, $newer->id])
        ->and($plan['applied'][$older->id])->toBe('400.00')
        ->and($plan['applied'][$newer->id])->toBe('100.00')
        ->and($plan['net'])->toBe('0.00');
});

it('draws fixed discounts from their remaining balance not original value', function () {
    $discount = grantDiscount($this->service, $this->world['student'], $this->world['admin']);
    $discount->forceFill(['remaining_amount' => '120.00', 'status' => DiscountStatus::PartiallyApplied])->save();

    $plan = $this->service->planApplication(collect([$discount->refresh()]), '500.00');

    expect($plan['applied'])->toBe([$discount->id => '120.00'])
        ->and($plan['net'])->toBe('380.00');
});

it('applies percentages to the ticket original and consumes no balance', function () {
    $discount = grantDiscount($this->service, $this->world['student'], $this->world['admin'], [
        'mode' => DiscountMode::Percentage,
        'value' => '50.00',
    ]);

    $plan = $this->service->planApplication(collect([$discount]), '300.00');

    expect($plan['applied'])->toBe([$discount->id => '150.00'])
        ->and($plan['net'])->toBe('150.00')
        ->and((float) $discount->remaining_amount)->toBeGreaterThanOrEqual(0);
});

it('rounds percentage results half-up to two decimals', function () {
    $discount = grantDiscount($this->service, $this->world['student'], $this->world['admin'], [
        'mode' => DiscountMode::Percentage,
        'value' => '33.33',
    ]);

    $plan = $this->service->planApplication(collect([$discount]), '100.01');

    // 100.01 × 33.33% = 33.3333...3 → 33.33 ; and on 100.02 → 33.3366… → 33.34
    expect($plan['applied'][$discount->id])->toBe('33.33');

    $plan2 = $this->service->planApplication(collect([$discount]), '100.02');
    expect($plan2['applied'][$discount->id])->toBe('33.34');
});

it('skips percentage application on zero-value tickets with a notice', function () {
    Log::spy();

    $discount = grantDiscount($this->service, $this->world['student'], $this->world['admin'], [
        'mode' => DiscountMode::Percentage,
        'value' => '50.00',
    ]);

    $plan = $this->service->planApplication(collect([$discount]), '0.00');

    expect($plan['applied'])->toBe([])
        ->and($plan['net'])->toBe('0.00')
        ->and($plan['notices'])->not->toBeEmpty();

    $this->service->logApplicationNotices($plan['notices']);
    Log::shouldHaveReceived('info')->withArgs(fn ($msg) => str_contains((string) $msg, 'خصم'));
});

it('plans mixed percentage then fixed with the cap at original', function () {
    $pct = grantDiscount($this->service, $this->world['student'], $this->world['admin'], [
        'mode' => DiscountMode::Percentage, 'value' => '50.00',
    ]);
    $fixed = grantDiscount($this->service, $this->world['student'], $this->world['admin'], [
        'value' => '400.00',
    ]);

    // percentage 150 off 300 → unapplied 150 → fixed capped at 150
    $plan = $this->service->planApplication(collect([$pct, $fixed])->sortBy(fn ($d) => $d->created_at), '300.00');

    expect($plan['applied'][$pct->id])->toBe('150.00')
        ->and($plan['applied'][$fixed->id])->toBe('150.00')
        ->and($plan['net'])->toBe('0.00');
});

// ── applyToTicket ──────────────────────────────────────────────────────────

it('stores the original/discount/net snapshot with usage rows on the ticket', function () {
    $student = $this->world['student'];
    $discount = grantDiscount($this->service, $student, $this->world['admin']);
    $ticket = issueTicket($student, $this->world['year'], 1200.00);

    $plan = $this->service->planApplication(collect([$discount]), '1200.00');
    $this->service->applyToTicket($ticket, $plan['applied'], $this->world['admin']);

    $ticket->refresh();
    expect((float) $ticket->original_amount)->toBe(1200.0)
        ->and((float) $ticket->discount_amount)->toBe(500.0)
        ->and((float) $ticket->amount)->toBe(700.0);

    $usage = $ticket->discountUsages()->firstOrFail();
    expect((float) $usage->applied_amount)->toBe(500.0)
        ->and($usage->applied_by)->toBe($this->world['admin']->id);

    $discount->refresh();
    expect($discount->status)->toBe(DiscountStatus::Exhausted)
        ->and((float) $discount->remaining_amount)->toBe(0.0);
});

it('leaves a partially applied discount eligible with its reduced remainder', function () {
    $student = $this->world['student'];
    $discount = grantDiscount($this->service, $student, $this->world['admin'], ['value' => '300.00']);
    $ticket = issueTicket($student, $this->world['year'], 200.00);

    $plan = $this->service->planApplication($this->service->eligibleFor($student, 'registration'), '200.00');
    $this->service->applyToTicket($ticket, $plan['applied'], $this->world['admin']);

    $discount->refresh();
    expect($discount->status)->toBe(DiscountStatus::PartiallyApplied)
        ->and((float) $discount->remaining_amount)->toBe(100.0);

    // second ticket draws only the remainder
    $ticket2 = issueTicket($student, $this->world['year'], 250.00);
    $plan2 = $this->service->planApplication($this->service->eligibleFor($student, 'registration'), '250.00');
    $this->service->applyToTicket($ticket2, $plan2['applied'], $this->world['admin']);

    $ticket2->refresh();
    expect((float) $ticket2->discount_amount)->toBe(100.0)
        ->and((float) $ticket2->amount)->toBe(150.0);
    $discount->refresh();
    expect($discount->status)->toBe(DiscountStatus::Exhausted);
});

it('never touches the ticket when there is nothing to apply', function () {
    $ticket = issueTicket($this->world['student'], $this->world['year'], 1500.00);

    $this->service->applyToTicket($ticket, [], $this->world['admin']);

    $ticket->refresh();
    expect($ticket->original_amount)->toBeNull()
        ->and((float) $ticket->discount_amount)->toBe(0.0)
        ->and((float) $ticket->amount)->toBe(1500.0);
});

it('ignores planned amounts that exceed the locked remaining balance', function () {
    $student = $this->world['student'];
    $discount = grantDiscount($this->service, $student, $this->world['admin'], ['value' => '300.00']);
    $ticket = issueTicket($student, $this->world['year'], 300.00);

    // stale plan built from the pre-drawdown balance of 300 while the true remainder is 100
    $discount->forceFill(['remaining_amount' => '100.00', 'status' => DiscountStatus::PartiallyApplied])->save();

    $this->service->applyToTicket($ticket, [$discount->id => '300.00'], $this->world['admin']);

    $ticket->refresh();
    expect((float) $ticket->discount_amount)->toBe(100.0)
        ->and((float) $ticket->amount)->toBe(200.0);
});
