<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Wallet>
 */
class WalletFactory extends Factory
{
    /**
     * The model that this factory builds.
     *
     * @var class-string<Wallet>
     */
    protected $model = Wallet::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'currency' => 'NGN',
            'balance' => 0,
        ];
    }
}
