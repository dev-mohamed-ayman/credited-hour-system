<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

test('login pages use the institute logo, font and brand stylesheet', function (string $loginRoute) {
    $this->get(route($loginRoute))
        ->assertOk()
        ->assertSee(asset('images/aboukir-logo.png'), false)
        ->assertSee(asset('images/logo-icon.png'), false)
        ->assertSee(asset('assets/css/brand.css'), false)
        ->assertSee('family=El+Messiri', false)
        ->assertDontSee('family=Public+Sans', false)
        ->assertSee('data-bs-theme="light"', false)
        ->assertDontSee('template-customizer.js', false);
})->with([
    'admin' => ['login'],
    'advisor' => ['advisor.login'],
    'student' => ['student.login'],
]);

test('admin dashboard sidebar shows the institute logo instead of the template brand', function () {
    Permission::firstOrCreate(['name' => 'dashboard.view', 'guard_name' => 'web']);

    $admin = User::factory()->create();
    $admin->givePermissionTo('dashboard.view');

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(asset('images/logo-icon.png'), false)
        ->assertSee(asset('assets/css/brand.css'), false)
        ->assertSee('نظام الساعات المعتمدة')
        ->assertDontSee('Vuexy');
});

test('admin dashboard is light only without the theme switcher or the template customizer', function () {
    Permission::firstOrCreate(['name' => 'dashboard.view', 'guard_name' => 'web']);

    $admin = User::factory()->create();
    $admin->givePermissionTo('dashboard.view');

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-bs-theme="light"', false)
        ->assertDontSee('data-bs-theme="dark"', false)
        ->assertDontSee('data-bs-theme-value', false)
        ->assertDontSee('template-customizer.js', false);
});
