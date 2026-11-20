<?php

namespace Database\Seeders;

use App\Enums\AdminRole;
use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Standard test super admin (no real credentials, Doc 07 §9).
        Admin::factory()->superAdmin()->create([
            'username' => 'superadmin',
            'email' => 'superadmin@korakdigital.com',
            'password' => Hash::make('adminPswd!!123'),
        ]);

        // Standard test admin.
        Admin::factory()->create([
            'username' => 'admin',
            'email' => 'admin@korakdigital.com',
            'role' => AdminRole::Admin,
            'password' => Hash::make('adminPswd!!123'),
        ]);
    }
}
