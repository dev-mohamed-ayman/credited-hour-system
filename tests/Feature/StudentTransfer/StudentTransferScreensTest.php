<?php

use App\Enums\RegistrationStatus;
use App\Enums\TransferRequestStatus;
use App\Livewire\Admin\StudentTransfer\Index as TransferIndex;
use App\Livewire\Admin\StudentTransfer\Show as TransferShow;
use App\Models\StudentTransferRequest;
use App\Models\User;
use App\Services\StudentTransferService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * An admin holding only the named transfer permissions, plus view.
 *
 * @param  array<int, string>  $actions
 */
function transferOperator(array $actions): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(array_merge(['student_transfers.view'], $actions));

    return $user;
}

// ------------------------------------------------------------------ the list

test('the list screen shows existing transfer requests', function () {
    $world = transferWorld();
    makeRequest($world);

    Livewire::actingAs($world['admin'])
        ->test(TransferIndex::class)
        ->assertOk()
        ->assertSee($world['student']->username)
        ->assertSee($world['targetDepartment']->name)
        ->assertSee(TransferRequestStatus::PENDING->label());
});

test('the list screen is closed to a user without view permission', function () {
    transferWorld();

    Livewire::actingAs(User::factory()->create())
        ->test(TransferIndex::class)
        ->assertForbidden();
});

// --------------------------------------------------------------- creating

test('an operator looks up a student and raises a pending request', function () {
    $world = transferWorld();
    $operator = transferOperator(['student_transfers.create']);

    Livewire::actingAs($operator)
        ->test(TransferIndex::class)
        ->set('studentCode', $world['student']->username)
        ->call('searchStudent')
        ->assertSet('student.id', $world['student']->id)
        ->set('toDepartmentId', $world['targetDepartment']->id)
        ->set('toSectionId', $world['targetSection']->id)
        ->set('toLevelId', $world['level']->id)
        ->set('reason', 'رغبة الطالب')
        ->call('createRequest');

    $request = StudentTransferRequest::first();

    expect($request)->not->toBeNull()
        ->and($request->status)->toBe(TransferRequestStatus::PENDING)
        ->and($request->to_section_id)->toBe($world['targetSection']->id)
        ->and($request->created_by_user_id)->toBe($operator->id);
});

test('raising a request without create permission is forbidden', function () {
    $world = transferWorld();

    Livewire::actingAs(transferOperator([]))
        ->test(TransferIndex::class)
        ->set('studentCode', $world['student']->username)
        ->call('searchStudent')
        ->set('toDepartmentId', $world['targetDepartment']->id)
        ->set('toSectionId', $world['targetSection']->id)
        ->set('toLevelId', $world['level']->id)
        ->call('createRequest')
        ->assertForbidden();

    expect(StudentTransferRequest::count())->toBe(0);
});

test('a second pending request is refused with a message rather than an error', function () {
    $world = transferWorld();
    makeRequest($world);

    Livewire::actingAs(transferOperator(['student_transfers.create']))
        ->test(TransferIndex::class)
        ->set('studentCode', $world['student']->username)
        ->call('searchStudent')
        ->set('toDepartmentId', $world['targetDepartment']->id)
        ->set('toSectionId', $world['targetSection']->id)
        ->set('toLevelId', $world['level']->id)
        ->call('createRequest')
        ->assertDispatched('alert');

    expect(StudentTransferRequest::count())->toBe(1);
});

// ------------------------------------------------------------- the details

test('the details screen shows what the transfer will reverse', function () {
    $world = transferWorld();
    fundWallet($world['student'], 5000, $world['year']);
    chargedRegistration($world);
    $ticket = issueTicket($world['student'], $world['year'], 2000, 'paid');

    Livewire::actingAs($world['admin'])
        ->test(TransferShow::class, ['transferRequest' => makeRequest($world)])
        ->assertOk()
        ->assertSee($world['student']->name)
        ->assertSee($ticket->ticket_number)
        ->assertSee('CS101')
        ->assertSee('3,100.00');
});

// ------------------------------------------------------------- the decision

test('an approver executes the transfer from the details screen', function () {
    $world = transferWorld();
    fundWallet($world['student'], 5000, $world['year']);
    $registration = chargedRegistration($world);
    $request = makeRequest($world);

    Livewire::actingAs(transferOperator(['student_transfers.approve']))
        ->test(TransferShow::class, ['transferRequest' => $request])
        ->call('approve')
        ->assertDispatched('alert');

    expect($request->refresh()->status)->toBe(TransferRequestStatus::APPROVED)
        ->and($registration->refresh()->status)->toBe(RegistrationStatus::CANCELLED)
        ->and(app(WalletService::class)->getBalance($world['student']))->toBe(5000.0)
        ->and($world['student']->refresh()->section_id)->toBe($world['targetSection']->id);
});

test('a viewer without approve permission cannot execute the transfer', function () {
    $world = transferWorld();
    $registration = chargedRegistration($world);
    $request = makeRequest($world);

    Livewire::actingAs(transferOperator(['student_transfers.reject']))
        ->test(TransferShow::class, ['transferRequest' => $request])
        ->call('approve')
        ->assertForbidden();

    expect($request->refresh()->status)->toBe(TransferRequestStatus::PENDING)
        ->and($registration->refresh()->status)->toBe(RegistrationStatus::APPROVED)
        ->and($world['student']->refresh()->section_id)->toBe($world['section']->id);
});

test('rejecting from the details screen requires a reason', function () {
    $world = transferWorld();
    $request = makeRequest($world);

    Livewire::actingAs(transferOperator(['student_transfers.reject']))
        ->test(TransferShow::class, ['transferRequest' => $request])
        ->call('reject')
        ->assertHasErrors(['rejectionReason' => 'required']);

    expect($request->refresh()->status)->toBe(TransferRequestStatus::PENDING);
});

test('an approver rejects the request with a reason', function () {
    $world = transferWorld();
    $request = makeRequest($world);

    Livewire::actingAs(transferOperator(['student_transfers.reject']))
        ->test(TransferShow::class, ['transferRequest' => $request])
        ->set('rejectionReason', 'المستندات غير مكتملة')
        ->call('reject')
        ->assertDispatched('alert');

    expect($request->refresh()->status)->toBe(TransferRequestStatus::REJECTED)
        ->and($request->rejection_reason)->toBe('المستندات غير مكتملة');
});

test('the decision panel disappears once the request is decided', function () {
    $world = transferWorld();
    $request = makeRequest($world);
    app(StudentTransferService::class)->reject($request, $world['admin'], 'غير موافق');

    Livewire::actingAs($world['admin'])
        ->test(TransferShow::class, ['transferRequest' => $request->refresh()])
        ->assertOk()
        ->assertDontSee('موافقة وتنفيذ التحويل')
        ->assertSee('غير موافق');
});

// ------------------------------------------------------------ full page render

test('both transfer pages render through the admin layout', function () {
    $world = transferWorld();
    $request = makeRequest($world);

    $this->actingAs($world['admin'])
        ->get(route('student-transfers.index'))
        ->assertOk()
        ->assertSee('طلبات تحويل التخصص', false);

    $this->actingAs($world['admin'])
        ->get(route('student-transfers.show', $request->id))
        ->assertOk()
        ->assertSee('تفاصيل طلب تحويل التخصص', false);
});
