<?php

namespace Database\Factories;

use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryType;
use App\Models\LedgerEntry;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LedgerEntry>
 */
class LedgerEntryFactory extends Factory
{
    /**
     * The model that this factory builds.
     *
     * @var class-string<LedgerEntry>
     */
    protected $model = LedgerEntry::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'wallet_id' => Wallet::factory(),
            'direction' => LedgerDirection::CREDIT,
            'type' => LedgerEntryType::FUNDING,
            'amount' => 50000,
            'balance_after' => 50000,
            'currency' => 'NGN',
            'reference' => (string) Str::uuid(),
            'description' => 'Wallet Funding',
            'metadata' => [],
            'created_at' => now(),
        ];
    }
}
