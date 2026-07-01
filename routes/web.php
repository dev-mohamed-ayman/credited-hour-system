<?php

use App\Http\Controllers\Admin\AcademicAdvisorController;
use App\Http\Controllers\Admin\CertificateTypeController;
use App\Http\Controllers\Admin\CityController;
use App\Http\Controllers\Admin\CountryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\LevelController;
use App\Http\Controllers\Admin\NationalityController;
use App\Http\Controllers\Admin\SectionController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\StudentController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Auth Routes (Guest only)
Route::middleware('guest')->group(function () {
    Route::get('login', \App\Livewire\Auth\Login::class)->name('login');
});

// Logout Route
Route::post('logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('login');
})->middleware('auth')->name('logout');

// Protected Routes — All require authentication
Route::middleware(['auth'])->group(function () {

    // Dashboard Routes
    Route::get('', [DashboardController::class, 'index'])->name('dashboard')->middleware('permission:dashboard.view');

    // Setting Routes
    Route::get('settings', [SettingController::class, 'index'])->name('setting.index')->middleware('permission:settings.view');
    Route::post('settings', [SettingController::class, 'update'])->name('setting.update')->middleware('permission:settings.edit');
    Route::get('registration-fees', \App\Livewire\Admin\RegistrationFee\Index::class)->name('registration-fees.index')->middleware('permission:registration_fees.view');
    Route::get('additional-fees', \App\Livewire\Admin\AdditionalFee\Index::class)->name('additional-fees.index')->middleware('permission:additional_fees.view');

    // Department Routes
    Route::resource('departments', DepartmentController::class)->except(['show'])->middleware('permission:departments.view');

    // Certificate Type Routes
    Route::resource('certificate-types', CertificateTypeController::class)->except(['show'])->middleware('permission:certificate_types.view');

    // Country Routes
    Route::resource('countries', CountryController::class)->except(['show'])->middleware('permission:countries.view');

    // City Routes
    Route::resource('cities', CityController::class)->except(['show'])->middleware('permission:cities.view');

    // Nationality Routes
    Route::resource('nationalities', NationalityController::class)->except(['show'])->middleware('permission:nationalities.view');

    // Section Routes
    Route::resource('sections', SectionController::class)->except(['show'])->middleware('permission:sections.view');

    // Level Routes
    Route::resource('levels', LevelController::class)->except(['show'])->middleware('permission:levels.view');

    // Year Routes
    Route::resource('years', \App\Http\Controllers\Admin\YearController::class)->except(['show'])->middleware('permission:years.view');
    Route::get('year-settings', \App\Livewire\Admin\YearSettings::class)->name('year-settings.index')->middleware('permission:years.edit');

    // Finance Routes
    Route::get('finance/fee-issuance', \App\Livewire\Admin\Finance\FeeIssuance::class)->name('admin.finance.fee-issuance')->middleware('permission:finance.view');
    Route::get('finance/fee-payment', \App\Livewire\Admin\Finance\FeePayment::class)->name('admin.finance.fee-payment')->middleware('permission:finance.view');
    Route::get('finance/student-financial-status', \App\Livewire\Admin\Finance\StudentFinancialStatus::class)->name('admin.finance.student-financial-status')->middleware('permission:finance.view');
    Route::get('finance/print-tickets', [\App\Http\Controllers\Admin\FinanceController::class, 'printTickets'])->name('admin.finance.print-tickets')->middleware('permission:finance.view');

    // Course Routes
    Route::resource('courses', \App\Http\Controllers\Admin\CourseController::class)->except(['show'])->middleware('permission:courses.view');

    // Grades Routes
    Route::resource('grades', \App\Http\Controllers\Admin\GradeController::class)->except(['show'])->middleware('permission:grades.view');
    Route::get('course-registration-settings', \App\Livewire\Admin\GradeSettings\Index::class)->name('course-registration-settings.index')->middleware('permission:course_registration_settings.view');
    Route::get('course-registrations', \App\Livewire\Admin\CourseRegistration\Index::class)->name('course-registrations.index')->middleware('permission:course_registrations.view');
    Route::get('registration-records', \App\Livewire\Admin\RegistrationRecord\Index::class)->name('registration-records.index')->middleware('permission:course_registrations.view');
    Route::get('registration-records/{registration}', \App\Livewire\Admin\RegistrationRecord\Show::class)->name('registration-records.show')->middleware('permission:course_registrations.view');

    // Student Routes
    Route::get('students/print-cards', [StudentController::class, 'printCardsIndex'])->name('print.student.cards.index')->middleware('permission:students.view');
    Route::post('students/print-cards', [StudentController::class, 'printCards'])->name('print.student.cards.print')->middleware('permission:students.view');
    Route::get('students/print-seat-numbers', [StudentController::class, 'printSeatNumbersIndex'])->name('print.seat.numbers.index')->middleware('permission:students.view');
    Route::post('students/print-seat-numbers', [StudentController::class, 'printSeatNumbers'])->name('print.seat.numbers.print')->middleware('permission:students.view');
    Route::get('students/print-certificates', [StudentController::class, 'printCertificatesIndex'])->name('print.certificates.index')->middleware('permission:students.view');
    Route::post('students/print-certificates', [StudentController::class, 'printCertificates'])->name('print.certificates.print')->middleware('permission:students.view');
    Route::get('students/print-report/{student}', [StudentController::class, 'printReport'])->name('students.print-report')->middleware('permission:students.view');
    Route::get('students/search', [\App\Http\Controllers\Admin\StudentController::class, 'searchIndex'])->name('students.search.index')->middleware('permission:students.view');
    Route::resource('students', StudentController::class)->middleware('permission:students.view');

    // Student Warning Routes
    Route::get('student-warnings', \App\Livewire\Admin\StudentWarning\Index::class)->name('student-warnings.index')->middleware('permission:student_warnings.view');
    Route::get('student-warnings/create', \App\Livewire\Admin\StudentWarning\Create::class)->name('student-warnings.create')->middleware('permission:student_warnings.create');

    // Student Search Route
    Route::get('student-search', \App\Livewire\Admin\StudentSearch::class)->name('student-search.index')->middleware('permission:students.view');

    // Academic Advisor Routes
    Route::resource('academic-advisors', AcademicAdvisorController::class)->except(['show'])->middleware('permission:academic_advisors.view');

    // Military Education Courses Routes
    Route::get('military-education-courses', \App\Livewire\Admin\MilitaryEducationCourses\Index::class)->name('military-education-courses.index')->middleware('permission:military_education.view');
    Route::get('military-education-courses/{militaryEducationCourse}', \App\Livewire\Admin\MilitaryEducationCourses\Show::class)->name('military-education-courses.show')->middleware('permission:military_education.view');

    // User Management Routes
    Route::get('users', \App\Livewire\Admin\User\Index::class)->name('users.index')->middleware('permission:users.view');
    Route::get('users/create', \App\Livewire\Admin\User\Form::class)->name('users.create')->middleware('permission:users.create');
    Route::get('users/{user}/edit', \App\Livewire\Admin\User\Form::class)->name('users.edit')->middleware('permission:users.edit');
});
// Advisor Routes
Route::prefix('advisor')->name('advisor.')->group(function () {
    Route::middleware('guest:advisor')->group(function () {
        Route::get('login', \App\Livewire\Advisor\Auth\Login::class)->name('login');
    });

    Route::middleware('auth:advisor')->group(function () {
        Route::post('logout', function () {
            Auth::guard('advisor')->logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();

            return redirect()->route('advisor.login');
        })->name('logout');

        Route::get('', \App\Livewire\Advisor\Dashboard::class)->name('dashboard');
        
        Route::get('course-registrations', \App\Livewire\Advisor\CourseRegistration\Index::class)->name('course-registrations.index');
        Route::get('registration-records', \App\Livewire\Advisor\RegistrationRecord\Index::class)->name('registration-records.index');
        Route::get('registration-records/{registration}', \App\Livewire\Advisor\RegistrationRecord\Show::class)->name('registration-records.show');
    });
});
