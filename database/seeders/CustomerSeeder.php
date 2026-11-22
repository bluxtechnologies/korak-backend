<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Standard test customer (no real credentials, Doc 07 §9).
        Customer::updateOrCreate(
            ['username' => 'test_customer'],
            [
                'email' => 'customer@example.com',
                'phone' => '+2348012345678',
                'phone_number' => '+2348012345678',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'device_verification_enabled' => false,
                'remember_token' => Str::random(10),
            ]
        );

        if (function_exists('fake') && app()->environment('local', 'testing') && Customer::count() < 6) {
            Customer::factory()->count(5)->create();
        }
    }
}
