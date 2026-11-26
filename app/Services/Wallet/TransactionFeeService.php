<?php

namespace App\Services\Wallet;

use App\Models\Setting;
use App\Models\TransactionFeeTier;
use Illuminate\Database\Eloquent\Collection;

class TransactionFeeService
{
    /**
     * Get all active fee tiers for a transaction type.
     *
     * @return Collection<int, TransactionFeeTier>
     */
    public function getTiers(string $type = 'deposit'): Collection
    {
        return TransactionFeeTier::query()
            ->active()
            ->forType($type)
            ->get();
    }

    /**
     * Find the matching active fee tier for the given amount.
     */
    public function findTierForAmount(int $amountInKobo, string $type = 'deposit'): ?TransactionFeeTier
    {
        $tiers = $this->getTiers($type);

        /** @var TransactionFeeTier|null $matched */
        $matched = $tiers->first(fn (TransactionFeeTier $tier): bool => $tier->matches($amountInKobo));

        return $matched;
    }

    /**
     * Calculate fee in kobo for a transaction amount.
     */
    public function calculateFee(int $amountInKobo, string $type = 'deposit'): int
    {
        $tier = $this->findTierForAmount($amountInKobo, $type);

        if ($tier !== null) {
            return $tier->fee;
        }

        // Standard fallback schedule if tiers are empty
        if ($amountInKobo < 100000) {
            return 2000;   // ₦20 (< ₦1,000)
        }

        if ($amountInKobo <= 1000000) {
            return 7500;   // ₦75 (₦1,000 - ₦10,000)
        }

        if ($amountInKobo <= 5000000) {
            return 15500;  // ₦155 (₦10,001 - ₦50,000)
        }

        if ($amountInKobo <= 10000000) {
            return 35000;  // ₦350 (₦50,001 - ₦100,000)
        }

        return 50000;      // ₦500 (> ₦100,000)
    }

    /**
     * Calculate deposit breakdown for frontend modal display (Fee added on top).
     *
     * @return array{
     *     requested_amount: int,
     *     requested_amount_formatted: string,
     *     fee: int,
     *     fee_formatted: string,
     *     total_payable: int,
     *     total_payable_formatted: string,
     *     tier_name: string
     * }
     */
    public function calculateDepositBreakdown(int $amountInKobo): array
    {
        $tier = $this->findTierForAmount($amountInKobo, 'deposit');
        $feeInKobo = $tier !== null ? $tier->fee : $this->calculateFee($amountInKobo, 'deposit');
        $totalPayable = $amountInKobo + $feeInKobo;

        return [
            'requested_amount' => $amountInKobo,
            'requested_amount_formatted' => $this->formatKobo($amountInKobo),
            'fee' => $feeInKobo,
            'fee_formatted' => $this->formatKobo($feeInKobo),
            'total_payable' => $totalPayable,
            'total_payable_formatted' => $this->formatKobo($totalPayable),
            'tier_name' => $tier !== null ? $tier->name : 'Standard Fee',
        ];
    }

    /**
     * Calculate breakdown for incoming direct bank transfer (Fee deducted from gross).
     *
     * @return array{
     *     gross_amount: int,
     *     gross_amount_formatted: string,
     *     fee: int,
     *     fee_formatted: string,
     *     net_credited: int,
     *     net_credited_formatted: string,
     *     tier_name: string
     * }
     */
    public function calculateIncomingTransferBreakdown(int $grossAmountInKobo): array
    {
        $tier = $this->findTierForAmount($grossAmountInKobo, 'deposit');
        $feeInKobo = $tier !== null ? $tier->fee : $this->calculateFee($grossAmountInKobo, 'deposit');
        $netCredited = max(0, $grossAmountInKobo - $feeInKobo);

        return [
            'gross_amount' => $grossAmountInKobo,
            'gross_amount_formatted' => $this->formatKobo($grossAmountInKobo),
            'fee' => $feeInKobo,
            'fee_formatted' => $this->formatKobo($feeInKobo),
            'net_credited' => $netCredited,
            'net_credited_formatted' => $this->formatKobo($netCredited),
            'tier_name' => $tier !== null ? $tier->name : 'Standard Fee',
        ];
    }

    /**
     * Sync active tiers to the settings table cache under deposit_fee_tiers.
     */
    public function syncSettings(): void
    {
        $tiers = $this->getTiers('deposit');

        $data = $tiers->map(fn (TransactionFeeTier $tier): array => [
            'id' => $tier->id,
            'name' => $tier->name,
            'min_amount' => $tier->min_amount,
            'max_amount' => $tier->max_amount,
            'fee' => $tier->fee,
            'min_amount_formatted' => $tier->min_amount_formatted,
            'max_amount_formatted' => $tier->max_amount_formatted,
            'fee_formatted' => $tier->fee_formatted,
            'priority' => $tier->priority,
        ])->values()->all();

        Setting::setValue('deposit_fee_tiers', $data);
    }

    /**
     * Format kobo into Naira string.
     */
    protected function formatKobo(int $kobo): string
    {
        return '₦'.number_format($kobo / 100, 2);
    }
}
