<?php

namespace Database\Factories;

use App\Enums\FundingStatus;
use App\Models\Customer;
use App\Models\FundingRecord;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FundingRecord>
 */
class FundingRecordFactory extends Factory
{
    /**
     * The model that this factory builds.
     *
     * @var class-string<FundingRecord>
     */
    protected $model = FundingRecord::class;

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
            'reference' => 'KORAK-FUND-'.strtoupper(Str::random(12)),
            'provider_reference' => 'fin_tx_'.Str::random(10),
            'amount' => 50000,
            'currency' => 'NGN',
            'status' => FundingStatus::PENDING,
            'checkout_url' => 'https://checkout.cofinance.ng/pay/test',
            'metadata' => [],
        ];
    }
}
