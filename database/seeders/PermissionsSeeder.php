<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionsSeeder extends Seeder
{
    /**
     * Seed permissions from config/permissions.php.
     * Only creates missing permissions — never deletes existing ones.
     */
    public function run(): void
    {
        $modules = config('permissions');

        foreach ($modules as $moduleKey => $module) {
            foreach ($module['actions'] as $actionKey => $actionLabel) {
                Permission::firstOrCreate(
                    ['name' => "{$moduleKey}.{$actionKey}", 'guard_name' => 'web']
                );
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
