<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerVirtualAccount;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CustomerVirtualAccount>
 */
class CustomerVirtualAccountFactory extends Factory
{
    /**
     * The model that this factory builds.
     *
     * @var class-string<CustomerVirtualAccount>
     */
    protected $model = CustomerVirtualAccount::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'wallet_id' => Wallet::factory(),
            'provider' => 'finitypay',
            'provider_account_id' => 'cust_'.Str::random(12),
            'account_reference' => 'ref_'.Str::random(16),
            'bank_name' => 'Wema Bank',
            'bank_code' => '035',
            'account_number' => fake()->numerify('##########'),
            'account_name' => fake()->name(),
            'status' => 'active',
            'metadata' => [],
        ];
    }
}
