<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DefaultAdminSeeder extends Seeder
{
    /**
     * Seed the default super admin user.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => config('app.default_admin_email')],
            [
                'name' => 'مدير النظام',
                'password' => Hash::make(config('app.default_admin_password')),
                'is_super_admin' => true,
            ]
        );
    }
}
