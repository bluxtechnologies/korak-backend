<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Standard test customer (no real credentials, Doc 07 §9).
        Customer::factory()->create([
            'username' => 'test_customer',
            'email' => 'customer@example.com',
        ]);

        Customer::factory()->count(5)->create();
    }
}
