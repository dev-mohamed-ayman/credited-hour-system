<?php

use App\Livewire\Admin\GradeSettings\Index;
use App\Livewire\Student\Dashboard;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

it('reads the graduation hours the dashboard shows from the setting', function () {
    Setting::query()->firstOrCreate([])->update(['graduation_required_hours' => 136]);

    expect((new Dashboard)->getGraduationRequiredHours())->toBe(136);
});

it('defaults to 132 graduation hours before any settings are saved', function () {
    expect(Setting::graduationRequiredHours())->toBe(132);
});

it('points the registration settings to where graduation hours are set now', function () {
    foreach (['course_registration_settings.view', 'course_registration_settings.edit'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    $admin = User::factory()->create();
    $admin->givePermissionTo(['course_registration_settings.view', 'course_registration_settings.edit']);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->assertSee('إعدادات الترقية والإنذارات')
        ->call('saveGeneralSettings')
        ->assertHasNoErrors();
});
