<?php

use App\Enums\RegistrationStatus;
use App\Enums\Semester;
use App\Enums\TransferRequestStatus;
use App\Enums\WalletTransactionType;
use App\Exceptions\TransferRequestException;
use App\Models\Registration;
use App\Models\StudentFeeTicket;
use App\Models\WalletTransaction;
use App\Models\Year;
use App\Services\StudentTransferService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ------------------------------------------------------------------ creation

test('a transfer request freezes where the student currently stands', function () {
    $world = transferWorld();

    $request = makeRequest($world);

    expect($request->status)->toBe(TransferRequestStatus::PENDING)
        ->and($request->from_department_id)->toBe($world['department']->id)
        ->and($request->from_section_id)->toBe($world['section']->id)
        ->and($request->to_department_id)->toBe($world['targetDepartment']->id)
        ->and($request->to_section_id)->toBe($world['targetSection']->id)
        ->and($request->year_id)->toBe($world['year']->id)
        ->and($request->semester)->toBe(Semester::FIRST)
        ->and($request->created_by_user_id)->toBe($world['admin']->id);
});

test('a student cannot have two pending transfer requests', function () {
    $world = transferWorld();
    makeRequest($world);

    makeRequest($world);
})->throws(TransferRequestException::class, 'يوجد طلب تحويل قيد المراجعة بالفعل لهذا الطالب.');

test('a transfer to the same section and level is refused', function () {
    $world = transferWorld();

    app(StudentTransferService::class)->create(
        student: $world['student'],
        toSectionId: $world['section']->id,
        toLevelId: $world['level']->id,
        reason: null,
        actor: $world['admin'],
    );
})->throws(TransferRequestException::class, 'الطالب موجود بالفعل في نفس الشعبة والفرقة.');

// ------------------------------------------------------------------- preview

test('the preview totals what the registrations and paid tickets will give back', function () {
    $world = transferWorld();
    fundWallet($world['student'], 5000, $world['year']);

    // 2 courses x 3 hours x 100 + 500 ministerial = 1100
    chargedRegistration($world);
    issueTicket($world['student'], $world['year'], 2000, 'paid');
    issueTicket($world['student'], $world['year'], 300, 'pending');

    $preview = app(StudentTransferService::class)->preview(makeRequest($world));

    expect($preview['registration_refund'])->toBe(1100.0)
        ->and($preview['ticket_refund'])->toBe(2000.0)
        ->and($preview['total_refund'])->toBe(3100.0)
        ->and($preview['wallet_balance_before'])->toBe(3900.0)
        ->and($preview['wallet_balance_after'])->toBe(7000.0)
        ->and($preview['registrations'])->toHaveCount(1)
        ->and($preview['registrations'][0]['courses'])->toHaveCount(2)
        ->and($preview['paid_tickets'])->toHaveCount(1)
        ->and($preview['pending_tickets'])->toHaveCount(1);
});

test('the preview shows how the two departments price the student differently', function () {
    $world = transferWorld();

    $preview = app(StudentTransferService::class)->preview(makeRequest($world));

    expect($preview['from_fee_setting'])->toBe(['hour_payment' => 100.0, 'ministerial_payment' => 500.0])
        ->and($preview['to_fee_setting'])->toBe(['hour_payment' => 250.0, 'ministerial_payment' => 700.0]);
});

test('the preview warns when the target department has no fees defined', function () {
    $world = transferWorld();
    \App\Models\RegistrationFee::query()
        ->where('department_id', $world['targetDepartment']->id)
        ->delete();

    $preview = app(StudentTransferService::class)->preview(makeRequest($world));

    expect($preview['to_fee_setting'])->toBeNull()
        ->and($preview['warnings'])->toContain('لا توجد مصاريف تسجيل مُعرّفة للتخصص والفرقة الجديدة. لن يستطيع الطالب تسجيل مواد قبل ضبطها.');
});

// ------------------------------------------------------------------ approval

test('approving refunds the registration charge and cancels the registration', function () {
    $world = transferWorld();
    fundWallet($world['student'], 5000, $world['year']);
    $registration = chargedRegistration($world);

    expect(app(WalletService::class)->getBalance($world['student']))->toBe(3900.0);

    app(StudentTransferService::class)->approve(makeRequest($world), $world['admin']);

    expect(app(WalletService::class)->getBalance($world['student']->refresh()))->toBe(5000.0)
        ->and($registration->refresh()->status)->toBe(RegistrationStatus::CANCELLED)
        ->and((float) $registration->charged_amount)->toBe(0.0);
});

test('approving refunds paid fee tickets to the wallet and cancels every ticket', function () {
    $world = transferWorld();
    $paid = issueTicket($world['student'], $world['year'], 2000, 'paid');
    $unpaid = issueTicket($world['student'], $world['year'], 300, 'pending');

    app(StudentTransferService::class)->approve(makeRequest($world), $world['admin']);

    expect(app(WalletService::class)->getBalance($world['student']))->toBe(2000.0)
        ->and($paid->refresh()->status)->toBe('cancelled')
        ->and($unpaid->refresh()->status)->toBe('cancelled');
});

test('every refund is traceable to the record it reverses and the admin who approved', function () {
    $world = transferWorld();
    $ticket = issueTicket($world['student'], $world['year'], 2000, 'paid');

    app(StudentTransferService::class)->approve(makeRequest($world), $world['admin']);

    $transaction = WalletTransaction::query()
        ->where('type', WalletTransactionType::REFUND)
        ->latest('id')
        ->first();

    expect($transaction->reference_id)->toBe($ticket->id)
        ->and($transaction->reference_type)->toBe($ticket->getMorphClass())
        ->and($transaction->performed_by_id)->toBe($world['admin']->id)
        ->and((float) $transaction->amount)->toBe(2000.0);
});

test('approving moves the student to the new section and level', function () {
    $world = transferWorld();
    $newLevel = \App\Models\Level::create(['name' => 'الفرقة الثانية']);

    $request = app(StudentTransferService::class)->create(
        student: $world['student'],
        toSectionId: $world['targetSection']->id,
        toLevelId: $newLevel->id,
        reason: null,
        actor: $world['admin'],
    );

    app(StudentTransferService::class)->approve($request, $world['admin']);

    $student = $world['student']->refresh();

    expect($student->section_id)->toBe($world['targetSection']->id)
        ->and($student->level_id)->toBe($newLevel->id)
        ->and($student->departmentId())->toBe($world['targetDepartment']->id);
});

test('an approved request stores a frozen snapshot of what it reversed', function () {
    $world = transferWorld();
    fundWallet($world['student'], 5000, $world['year']);
    chargedRegistration($world);
    issueTicket($world['student'], $world['year'], 2000, 'paid');

    $request = makeRequest($world);
    app(StudentTransferService::class)->approve($request, $world['admin']);
    $request->refresh();

    expect($request->status)->toBe(TransferRequestStatus::APPROVED)
        ->and((float) $request->refunded_amount)->toBe(3100.0)
        ->and($request->decided_by_user_id)->toBe($world['admin']->id)
        ->and($request->decided_at)->not->toBeNull()
        ->and((float) $request->reversal_snapshot['total_refund'])->toBe(3100.0)
        ->and($request->reversal_snapshot['registrations'])->toHaveCount(1);

    // The snapshot is what the details screen shows from now on — it must not be recomputed.
    expect((float) app(StudentTransferService::class)->preview($request)['total_refund'])->toBe(3100.0);
});

test('a decided request cannot be approved again', function () {
    $world = transferWorld();
    $request = makeRequest($world);
    app(StudentTransferService::class)->approve($request, $world['admin']);

    app(StudentTransferService::class)->approve($request->refresh(), $world['admin']);
})->throws(TransferRequestException::class, 'تم البت في هذا الطلب بالفعل.');

test('registrations and tickets outside the requested term are left alone', function () {
    $world = transferWorld();
    fundWallet($world['student'], 5000, $world['year']);

    $otherYear = Year::create([
        'year' => '2024-2025',
        'first_semester_status' => \App\Enums\SemesterStatus::DISABLED,
        'second_semester_status' => \App\Enums\SemesterStatus::DISABLED,
        'summer_semester_status' => \App\Enums\SemesterStatus::DISABLED,
    ]);

    $pastRegistration = Registration::create([
        'student_id' => $world['student']->id,
        'year_id' => $otherYear->id,
        'semester' => Semester::FIRST,
        'status' => RegistrationStatus::APPROVED,
        'charged_amount' => 800,
    ]);

    $pastTicket = StudentFeeTicket::create([
        'ticket_number' => 'T-OLD',
        'student_id' => $world['student']->id,
        'fee_type' => 'registration',
        'fee_id' => 1,
        'fee_name' => 'مصاريف عام سابق',
        'amount' => 1500,
        'status' => 'paid',
        'year_id' => $otherYear->id,
        'semester' => Semester::FIRST->value,
    ]);

    app(StudentTransferService::class)->approve(makeRequest($world), $world['admin']);

    expect($pastRegistration->refresh()->status)->toBe(RegistrationStatus::APPROVED)
        ->and((float) $pastRegistration->charged_amount)->toBe(800.0)
        ->and($pastTicket->refresh()->status)->toBe('paid')
        ->and(app(WalletService::class)->getBalance($world['student']))->toBe(5000.0);
});

// ------------------------------------------------------------------ rejection

test('rejecting moves no money and leaves the student where they are', function () {
    $world = transferWorld();
    fundWallet($world['student'], 5000, $world['year']);
    $registration = chargedRegistration($world);
    $ticket = issueTicket($world['student'], $world['year'], 2000, 'paid');

    $request = makeRequest($world);
    app(StudentTransferService::class)->reject($request, $world['admin'], 'المستندات غير مكتملة');
    $request->refresh();

    expect($request->status)->toBe(TransferRequestStatus::REJECTED)
        ->and($request->rejection_reason)->toBe('المستندات غير مكتملة')
        ->and($world['student']->refresh()->section_id)->toBe($world['section']->id)
        ->and($registration->refresh()->status)->toBe(RegistrationStatus::APPROVED)
        ->and($ticket->refresh()->status)->toBe('paid')
        ->and(app(WalletService::class)->getBalance($world['student']))->toBe(3900.0);
});
