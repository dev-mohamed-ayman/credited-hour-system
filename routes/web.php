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
use Illuminate\Support\Facades\Route;

// Dashboard Routes
Route::get('', [DashboardController::class, 'index'])->name('dashboard');

// Setting Routes
Route::get('settings', [SettingController::class, 'index'])->name('setting.index');
Route::post('settings', [SettingController::class, 'update'])->name('setting.update');

// Department Routes
Route::resource('departments', DepartmentController::class)->except(['show']);

// Certificate Type Routes
Route::resource('certificate-types', CertificateTypeController::class)->except(['show']);

// Country Routes
Route::resource('countries', CountryController::class)->except(['show']);

// City Routes
Route::resource('cities', CityController::class)->except(['show']);

// Nationality Routes
Route::resource('nationalities', NationalityController::class)->except(['show']);

// Section Routes
Route::resource('sections', SectionController::class)->except(['show']);

// Level Routes
Route::resource('levels', LevelController::class)->except(['show']);

// Year Routes
Route::resource('years', \App\Http\Controllers\Admin\YearController::class)->except(['show']);

// Course Routes
Route::resource('courses', \App\Http\Controllers\Admin\CourseController::class)->except(['show']);

// Student Routes
Route::get('students/print-cards', [StudentController::class, 'printCardsIndex'])->name('print.student.cards.index');
Route::post('students/print-cards', [StudentController::class, 'printCards'])->name('print.student.cards.print');
Route::resource('students', StudentController::class);

// Academic Advisor Routes
Route::resource('academic-advisors', AcademicAdvisorController::class)->except(['show']);
