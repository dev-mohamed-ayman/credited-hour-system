<?php

use App\Livewire\Admin\User\Form as UserForm;
use App\Livewire\Admin\User\Index as UserIndex;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed essential permissions for testing
    Permission::firstOrCreate(['name' => 'users.view', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'users.create', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'users.edit', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'users.delete', 'guard_name' => 'web']);
});

test('unauthorized users cannot view user list', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('users.index'));
    $response->assertStatus(403);
});

test('authorized users can view user list', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('users.view');

    $response = $this->actingAs($user)->get(route('users.index'));
    $response->assertStatus(200);
});

test('super admin has bypass and can view user list', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $response = $this->actingAs($superAdmin)->get(route('users.index'));
    $response->assertStatus(200);
});

test('user can be created by authorized personnel', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo(['users.view', 'users.create']);

    Livewire::actingAs($admin)
        ->test(UserForm::class)
        ->set('name', 'New Administrator')
        ->set('email', 'new_admin@example.com')
        ->set('password', 'password123')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('users', [
        'name' => 'New Administrator',
        'email' => 'new_admin@example.com',
    ]);
});

test('user can be edited and permissions can be updated', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo(['users.view', 'users.edit']);

    $targetUser = User::factory()->create([
        'name' => 'Old Name',
    ]);

    Livewire::actingAs($admin)
        ->test(UserForm::class, ['user' => $targetUser])
        ->set('name', 'Updated Name')
        ->set('selectedPermissions', ['users.view'])
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('users', [
        'id' => $targetUser->id,
        'name' => 'Updated Name',
    ]);

    expect($targetUser->fresh()->hasPermissionTo('users.view'))->toBeTrue();
});

test('user cannot delete themselves', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo(['users.view', 'users.delete']);

    Livewire::actingAs($admin)
        ->test(UserIndex::class)
        ->call('delete', $admin->id)
        ->assertDispatched('toast');

    $this->assertDatabaseHas('users', ['id' => $admin->id]);
});

test('user can delete other user', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo(['users.view', 'users.delete']);

    $targetUser = User::factory()->create();

    Livewire::actingAs($admin)
        ->test(UserIndex::class)
        ->call('delete', $targetUser->id)
        ->assertDispatched('toast');

    $this->assertDatabaseMissing('users', ['id' => $targetUser->id]);
});
