<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

test('unauthenticated users are redirected to login', function () {
    $response = $this->get('/');
    $response->assertRedirect('/login');
});

test('authenticated users with permission can access dashboard', function () {
    $user = User::factory()->create();

    // Seed and grant permission
    Permission::firstOrCreate(['name' => 'dashboard.view', 'guard_name' => 'web']);
    $user->givePermissionTo('dashboard.view');

    $response = $this->actingAs($user)->get('/');
    $response->assertStatus(200);
});
