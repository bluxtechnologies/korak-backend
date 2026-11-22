<?php

namespace Database\Seeders;

use App\Enums\AdminRole;
use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Standard test super admin (no real credentials, Doc 07 §9).
        Admin::updateOrCreate(
            ['username' => 'superadmin'],
            [
                'email' => 'superadmin@korakdigital.com',
                'email_verified_at' => now(),
                'password' => Hash::make('adminPswd!!123'),
                'role' => AdminRole::SuperAdmin,
                'is_active' => true,
                'remember_token' => Str::random(10),
            ]
        );

        // Standard test admin.
        Admin::updateOrCreate(
            ['username' => 'admin'],
            [
                'email' => 'admin@korakdigital.com',
                'email_verified_at' => now(),
                'password' => Hash::make('adminPswd!!123'),
                'role' => AdminRole::Admin,
                'is_active' => true,
                'remember_token' => Str::random(10),
            ]
        );
    }
}
