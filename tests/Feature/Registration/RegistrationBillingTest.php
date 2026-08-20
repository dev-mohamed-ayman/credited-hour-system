<?php

use App\Enums\RegistrationStatus;
use App\Enums\Semester;
use App\Enums\WalletTransactionType;
use App\Livewire\Admin\RegistrationRecord\Show as AdminRegistrationShow;
use App\Livewire\Student\CourseRegistration\Index as StudentRegistrationIndex;
use App\Models\Registration;
use App\Services\RegistrationBillingService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------- the fee gate

test('a student with an unpaid fee ticket cannot register courses', function () {
    ['student' => $student, 'year' => $year, 'courses' => $courses] = billingWorld();
    issueTicket($student, $year);
    fundWallet($student, 5000, $year);

    Livewire::actingAs($student, 'student')
        ->test(StudentRegistrationIndex::class)
        ->set('selectedDue', [$courses[0]->id])
        ->call('save');

    expect(Registration::count())->toBe(0)
        ->and(\App\Models\RegistrationCourse::count())->toBe(0);
});

test('a cancelled fee ticket is not a debt and does not block registration', function () {
    ['student' => $student, 'year' => $year, 'courses' => $courses] = billingWorld();
    issueTicket($student, $year, status: 'cancelled');

    Livewire::actingAs($student, 'student')
        ->test(StudentRegistrationIndex::class)
        ->set('selectedDue', [$courses[0]->id])
        ->call('save');

    expect(\App\Models\RegistrationCourse::count())->toBe(1);
});

test('paying a fee ticket clears the gate and credits the wallet', function () {
    ['student' => $student, 'year' => $year] = billingWorld();
    $ticket = issueTicket($student, $year, 2000);

    $billing = app(RegistrationBillingService::class);
    expect($billing->hasOutstandingFees($student))->toBeTrue();

    $ticket->update(['status' => 'paid', 'paid_at' => now()]);
    app(WalletService::class)->deposit(
        student: $student, amount: 2000, yearId: $year->id,
        semester: Semester::FIRST, reason: 'سداد حافظة', reference: $ticket,
    );

    expect($billing->hasOutstandingFees($student->refresh()))->toBeFalse()
        ->and(app(WalletService::class)->getBalance($student))->toBe(2000.0);
});

// ------------------------------------------------------- who pays and when

test('a student registration is pending and charges nothing until approved', function () {
    ['student' => $student, 'year' => $year, 'courses' => $courses] = billingWorld();
    fundWallet($student, 5000, $year);

    Livewire::actingAs($student, 'student')
        ->test(StudentRegistrationIndex::class)
        ->set('selectedDue', [$courses[0]->id])
        ->call('save');

    $registration = Registration::first();

    expect($registration->status)->toBe(RegistrationStatus::PENDING)
        ->and((float) $registration->charged_amount)->toBe(0.0)
        ->and(app(WalletService::class)->getBalance($student))->toBe(5000.0);
});

test('an admin registration is approved and charges the wallet immediately', function () {
    ['student' => $student, 'admin' => $admin, 'year' => $year, 'courses' => $courses] = billingWorld();
    fundWallet($student, 5000, $year);

    Livewire::actingAs($admin)
        ->test(\App\Livewire\Admin\CourseRegistration\Index::class)
        ->set('searchCode', $student->username)
        ->call('search')
        ->set('selectedDue', [$courses[0]->id])
        ->call('save');

    $registration = Registration::first();

    // 3 hours * 100 + 500 ministerial = 800
    expect($registration->status)->toBe(RegistrationStatus::APPROVED)
        ->and((float) $registration->charged_amount)->toBe(800.0)
        ->and(app(WalletService::class)->getBalance($student->refresh()))->toBe(4200.0);
});

test('an admin registration is refused outright when the wallet cannot cover it', function () {
    ['student' => $student, 'admin' => $admin, 'year' => $year, 'courses' => $courses] = billingWorld();
    fundWallet($student, 100, $year);

    Livewire::actingAs($admin)
        ->test(\App\Livewire\Admin\CourseRegistration\Index::class)
        ->set('searchCode', $student->username)
        ->call('search')
        ->set('selectedDue', [$courses[0]->id])
        ->call('save');

    expect(\App\Models\RegistrationCourse::count())->toBe(0)
        ->and(app(WalletService::class)->getBalance($student->refresh()))->toBe(100.0);
});

// ------------------------------------------------------------ the settlement

test('approving a pending registration charges hours plus the ministerial fee', function () {
    ['student' => $student, 'admin' => $admin, 'year' => $year, 'courses' => $courses] = billingWorld();
    fundWallet($student, 5000, $year);

    Livewire::actingAs($student, 'student')
        ->test(StudentRegistrationIndex::class)
        ->set('selectedDue', [$courses[0]->id, $courses[1]->id])
        ->call('save');

    $registration = Registration::first();

    Livewire::actingAs($admin)
        ->test(AdminRegistrationShow::class, ['registration' => $registration])
        ->call('approveRegistration');

    // 6 hours * 100 + 500 = 1100
    expect((float) $registration->refresh()->charged_amount)->toBe(1100.0)
        ->and(app(WalletService::class)->getBalance($student->refresh()))->toBe(3900.0);
});

test('approving twice does not charge twice', function () {
    ['student' => $student, 'admin' => $admin, 'year' => $year, 'courses' => $courses] = billingWorld();
    fundWallet($student, 5000, $year);

    Livewire::actingAs($student, 'student')
        ->test(StudentRegistrationIndex::class)
        ->set('selectedDue', [$courses[0]->id])
        ->call('save');

    $registration = Registration::first();
    $component = Livewire::actingAs($admin)->test(AdminRegistrationShow::class, ['registration' => $registration]);

    $component->call('approveRegistration');
    $component->call('approveRegistration');

    expect((float) $registration->refresh()->charged_amount)->toBe(800.0)
        ->and(app(WalletService::class)->getBalance($student->refresh()))->toBe(4200.0);
});

test('adding a course to an approved registration charges only the extra hours', function () {
    ['student' => $student, 'admin' => $admin, 'year' => $year, 'courses' => $courses] = billingWorld();
    fundWallet($student, 5000, $year);

    Livewire::actingAs($admin)
        ->test(\App\Livewire\Admin\CourseRegistration\Index::class)
        ->set('searchCode', $student->username)
        ->call('search')
        ->set('selectedDue', [$courses[0]->id])
        ->call('save');

    $registration = Registration::first();
    expect((float) $registration->charged_amount)->toBe(800.0);

    Livewire::actingAs($admin)
        ->test(AdminRegistrationShow::class, ['registration' => $registration])
        ->set('selectedCourseId', $courses[1]->id)
        ->call('addCourse');

    // 300 more for the extra 3 hours; the 500 ministerial fee is NOT charged again.
    expect((float) $registration->refresh()->charged_amount)->toBe(1100.0)
        ->and(app(WalletService::class)->getBalance($student->refresh()))->toBe(3900.0);
});

test('removing a course from an approved registration refunds the difference', function () {
    ['student' => $student, 'admin' => $admin, 'year' => $year, 'courses' => $courses] = billingWorld();
    fundWallet($student, 5000, $year);

    Livewire::actingAs($admin)
        ->test(\App\Livewire\Admin\CourseRegistration\Index::class)
        ->set('searchCode', $student->username)
        ->call('search')
        ->set('selectedDue', [$courses[0]->id, $courses[1]->id])
        ->call('save');

    $registration = Registration::first();
    expect((float) $registration->charged_amount)->toBe(1100.0);

    $registrationCourse = $registration->courses()->first();

    Livewire::actingAs($admin)
        ->test(AdminRegistrationShow::class, ['registration' => $registration])
        ->call('deleteCourse', $registrationCourse->id);

    expect((float) $registration->refresh()->charged_amount)->toBe(800.0)
        ->and(app(WalletService::class)->getBalance($student->refresh()))->toBe(4200.0);
});

test('rejecting a charged registration refunds everything it was charged', function () {
    ['student' => $student, 'admin' => $admin, 'year' => $year, 'courses' => $courses] = billingWorld();
    fundWallet($student, 5000, $year);

    Livewire::actingAs($admin)
        ->test(\App\Livewire\Admin\CourseRegistration\Index::class)
        ->set('searchCode', $student->username)
        ->call('search')
        ->set('selectedDue', [$courses[0]->id])
        ->call('save');

    $registration = Registration::first();

    Livewire::actingAs($admin)
        ->test(AdminRegistrationShow::class, ['registration' => $registration])
        ->set('rejectionReason', 'خطأ في التسجيل')
        ->call('rejectRegistration');

    expect($registration->refresh()->status)->toBe(RegistrationStatus::REJECTED)
        ->and((float) $registration->charged_amount)->toBe(0.0)
        ->and(app(WalletService::class)->getBalance($student->refresh()))->toBe(5000.0);
});

test('refunds are recorded as refunds, not deposits', function () {
    ['student' => $student, 'admin' => $admin, 'year' => $year, 'courses' => $courses] = billingWorld();
    fundWallet($student, 5000, $year);

    Livewire::actingAs($admin)
        ->test(\App\Livewire\Admin\CourseRegistration\Index::class)
        ->set('searchCode', $student->username)
        ->call('search')
        ->set('selectedDue', [$courses[0]->id])
        ->call('save');

    $registration = Registration::first();

    Livewire::actingAs($admin)
        ->test(AdminRegistrationShow::class, ['registration' => $registration])
        ->set('rejectionReason', 'خطأ')
        ->call('rejectRegistration');

    expect($student->walletTransactions()->where('type', WalletTransactionType::REFUND)->count())->toBe(1)
        ->and($student->walletTransactions()->where('type', WalletTransactionType::WITHDRAWAL)->count())->toBe(1);
});

// ------------------------------------------------------------------ the screens

test('the registration screen warns about outstanding fees and blocks saving', function () {
    ['student' => $student, 'year' => $year] = billingWorld();
    issueTicket($student, $year, 2000);

    Livewire::actingAs($student, 'student')
        ->test(StudentRegistrationIndex::class)
        ->assertSet('registrationAvailable', true)
        ->assertSee('التسجيل موقوف')
        ->assertSee('مصاريف غير مدفوعة')
        ->assertSee('2,000.00');
});

test('the registration screen shows the cost of the current selection', function () {
    ['student' => $student, 'year' => $year, 'courses' => $courses] = billingWorld();
    fundWallet($student, 5000, $year);

    Livewire::actingAs($student, 'student')
        ->test(StudentRegistrationIndex::class)
        ->set('selectedDue', [$courses[0]->id])
        ->assertSee('تكلفة التسجيل')
        ->assertSee('الرسوم الوزارية')
        ->assertDontSee('التسجيل موقوف');
});

test('the cost panel flags a selection the wallet cannot cover', function () {
    ['student' => $student, 'year' => $year, 'courses' => $courses] = billingWorld();
    fundWallet($student, 100, $year);

    Livewire::actingAs($student, 'student')
        ->test(StudentRegistrationIndex::class)
        ->set('selectedDue', [$courses[0]->id])
        ->assertSee('الرصيد لا يغطي تكلفة المواد المختارة');
});

test('the registration record shows what the wallet was charged', function () {
    ['student' => $student, 'admin' => $admin, 'year' => $year, 'courses' => $courses] = billingWorld();
    fundWallet($student, 5000, $year);

    Livewire::actingAs($admin)
        ->test(\App\Livewire\Admin\CourseRegistration\Index::class)
        ->set('searchCode', $student->username)
        ->call('search')
        ->set('selectedDue', [$courses[0]->id])
        ->call('save');

    Livewire::actingAs($admin)
        ->test(AdminRegistrationShow::class, ['registration' => Registration::first()])
        ->assertSee('الرسوم المخصومة من المحفظة')
        ->assertSee('800.00');
});

test('a student adding courses to an approved registration sends it back for review without charging', function () {
    ['student' => $student, 'admin' => $admin, 'year' => $year, 'courses' => $courses] = billingWorld();
    fundWallet($student, 5000, $year);

    Livewire::actingAs($admin)
        ->test(\App\Livewire\Admin\CourseRegistration\Index::class)
        ->set('searchCode', $student->username)
        ->call('search')
        ->set('selectedDue', [$courses[0]->id])
        ->call('save');

    $registration = Registration::first();
    expect((float) $registration->charged_amount)->toBe(800.0);

    Livewire::actingAs($student, 'student')
        ->test(StudentRegistrationIndex::class)
        ->set('selectedDue', [$courses[1]->id])
        ->call('save');

    $registration->refresh();

    // Back to pending, and no money moved without staff approval.
    expect($registration->status)->toBe(RegistrationStatus::PENDING)
        ->and((float) $registration->charged_amount)->toBe(800.0)
        ->and(app(WalletService::class)->getBalance($student->refresh()))->toBe(4200.0)
        ->and($registration->courses()->count())->toBe(2);

    // Approving then settles only the extra 3 hours.
    Livewire::actingAs($admin)
        ->test(AdminRegistrationShow::class, ['registration' => $registration])
        ->call('approveRegistration');

    expect((float) $registration->refresh()->charged_amount)->toBe(1100.0)
        ->and(app(WalletService::class)->getBalance($student->refresh()))->toBe(3900.0);
});

test('an advisor approving a registration charges the wallet', function () {
    ['student' => $student, 'advisor' => $advisor, 'year' => $year, 'courses' => $courses] = billingWorld();
    $student->update(['academic_advisor_id' => $advisor->id]);
    fundWallet($student, 5000, $year);

    Livewire::actingAs($student, 'student')
        ->test(StudentRegistrationIndex::class)
        ->set('selectedDue', [$courses[0]->id])
        ->call('save');

    $registration = Registration::first();

    Livewire::actingAs($advisor, 'advisor')
        ->test(\App\Livewire\Advisor\RegistrationRecord\Show::class, ['registration' => $registration])
        ->call('approveRegistration');

    expect($registration->refresh()->status)->toBe(RegistrationStatus::APPROVED)
        ->and((float) $registration->charged_amount)->toBe(800.0)
        ->and(app(WalletService::class)->getBalance($student->refresh()))->toBe(4200.0);
});

test('approval is refused and left pending when the wallet cannot cover the charge', function () {
    ['student' => $student, 'admin' => $admin, 'year' => $year, 'courses' => $courses] = billingWorld();
    fundWallet($student, 100, $year);

    Livewire::actingAs($student, 'student')
        ->test(StudentRegistrationIndex::class)
        ->set('selectedDue', [$courses[0]->id])
        ->call('save');

    $registration = Registration::first();

    Livewire::actingAs($admin)
        ->test(AdminRegistrationShow::class, ['registration' => $registration])
        ->call('approveRegistration');

    expect($registration->refresh()->status)->toBe(RegistrationStatus::PENDING)
        ->and((float) $registration->charged_amount)->toBe(0.0)
        ->and(app(WalletService::class)->getBalance($student->refresh()))->toBe(100.0);
});

test('the student dashboard says registration is blocked and lists what is owed', function () {
    ['student' => $student, 'year' => $year] = billingWorld();
    issueTicket($student, $year, 1500);

    Livewire::actingAs($student, 'student')
        ->test(\App\Livewire\Student\Dashboard::class)
        ->assertSee('تسجيل المواد موقوف')
        ->assertSee('1,500.00');
});
