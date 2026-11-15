<?php

namespace Database\Seeders;

use App\Enums\AdminRole;
use App\Models\Admin;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Standard test super admin (no real credentials, Doc 07 §9).
        Admin::factory()->superAdmin()->create([
            'username' => 'test_superadmin',
            'email' => 'superadmin@example.com',
        ]);

        // Standard test admin.
        Admin::factory()->create([
            'username' => 'test_admin',
            'email' => 'admin@example.com',
            'role' => AdminRole::Admin,
        ]);
    }
}
