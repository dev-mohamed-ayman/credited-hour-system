<?php

use App\Enums\DiscountMode;
use App\Enums\DiscountScope;
use App\Enums\Semester;
use App\Livewire\Admin\Finance\FeeIssuance;
use App\Models\AdditionalFee;
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
});

function additionalCardFee($world): AdditionalFee
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

it('applies a percentage discount on an additional fee at issuance and freezes it (BR-5)', function () {
    $fee = additionalCardFee($this->world);

    $this->service->grant([
        'student_id' => $this->world['student']->id,
        'scope' => DiscountScope::Additional->value,
        'fee_id' => $fee->id,
        'year_id' => $this->world['year']->id,
        'semester' => Semester::FIRST->value,
        'mode' => DiscountMode::Percentage->value,
        'value' => '50.00',
        'reason' => 'خصم بطاقة',
    ], $this->world['admin']);

    Livewire::actingAs($this->world['admin'])
        ->test(FeeIssuance::class)
        ->set('studentCode', $this->world['student']->username)
        ->call('searchStudent')
        ->set('selectedFees', ['additional-'.$fee->id])
        ->call('generateTickets');

    $ticket = StudentFeeTicket::where('fee_type', 'additional')->firstOrFail();
    expect((float) $ticket->original_amount)->toBe(300.0)
        ->and((float) $ticket->discount_amount)->toBe(150.0)
        ->and((float) $ticket->amount)->toBe(150.0);

    // Underlying fee definition rises afterward — the issued ticket must not move (BR-5).
    $fee->update(['amount' => 900]);

    $ticket->refresh();
    expect((float) $ticket->amount)->toBe(150.0)
        ->and((float) $ticket->original_amount)->toBe(300.0);
});

it('renders the discount line with its reason on the printed receipt (FR-012, SC-004)', function () {
    $fee = additionalCardFee($this->world);

    $this->service->grant([
        'student_id' => $this->world['student']->id,
        'scope' => DiscountScope::Additional->value,
        'fee_id' => $fee->id,
        'year_id' => $this->world['year']->id,
        'semester' => Semester::FIRST->value,
        'mode' => DiscountMode::Fixed->value,
        'value' => '100.00',
        'reason' => 'منحة التفوق العلمي',
    ], $this->world['admin']);

    Livewire::actingAs($this->world['admin'])
        ->test(FeeIssuance::class)
        ->set('studentCode', $this->world['student']->username)
        ->call('searchStudent')
        ->set('selectedFees', ['additional-'.$fee->id])
        ->call('generateTickets');

    $ticket = StudentFeeTicket::where('fee_type', 'additional')->firstOrFail();

    $this->actingAs($this->world['admin'])
        ->get(route('admin.finance.print-tickets', ['tickets' => $ticket->ticket_number]))
        ->assertOk()
        ->assertSee('خصم')
        ->assertSee('منحة التفوق العلمي')
        ->assertSee(number_format(100.00, 2));
});
