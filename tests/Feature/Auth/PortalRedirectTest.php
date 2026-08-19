<?php

use App\Models\AcademicAdvisor;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function advisor(): AcademicAdvisor
{
    return AcademicAdvisor::create([
        'name' => fake()->name(),
        'username' => fake()->unique()->userName(),
        'password' => Hash::make('password'),
        'max_students' => 50,
    ]);
}

test('guests are sent to the login page of the portal they requested', function (string $route, string $loginRoute) {
    $this->get(route($route))->assertRedirect(route($loginRoute));
})->with([
    'student portal' => ['student.dashboard', 'student.login'],
    'student page' => ['student.change-password', 'student.login'],
    'advisor portal' => ['advisor.dashboard', 'advisor.login'],
    'admin portal' => ['dashboard', 'login'],
]);

test('authenticated students are sent back to their dashboard from the student login page', function () {
    $this->actingAs(Student::factory()->create(), 'student')
        ->get(route('student.login'))
        ->assertRedirect(route('student.dashboard'));
});

test('authenticated advisors are sent back to their dashboard from the advisor login page', function () {
    $this->actingAs(advisor(), 'advisor')
        ->get(route('advisor.login'))
        ->assertRedirect(route('advisor.dashboard'));
});

test('authenticated admins are sent back to the admin dashboard from the admin login page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('login'))
        ->assertRedirect(route('dashboard'));
});
