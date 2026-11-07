<?php

use App\Livewire\Admin\GradeSettings\Index;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['course_registration_settings.view', 'course_registration_settings.edit'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo(['course_registration_settings.view', 'course_registration_settings.edit']);
});

it('shows the stored graduation hours', function () {
    Setting::query()->firstOrCreate([])->update(['graduation_required_hours' => 136]);

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->assertSet('graduationRequiredHours', 136);
});

it('saves the graduation hours with the general settings', function () {
    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->set('graduationRequiredHours', 144)
        ->call('saveGeneralSettings')
        ->assertHasNoErrors();

    expect(Setting::graduationRequiredHours())->toBe(144);
});

it('requires a positive whole number of graduation hours', function (mixed $hours, string $rule) {
    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->set('graduationRequiredHours', $hours)
        ->call('saveGeneralSettings')
        ->assertHasErrors(['graduationRequiredHours' => $rule]);
})->with([
    'empty' => ['', 'required'],
    'zero' => [0, 'min'],
    'too many' => [501, 'max'],
]);
