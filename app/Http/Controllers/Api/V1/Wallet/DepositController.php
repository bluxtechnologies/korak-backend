<?php

namespace App\Http\Controllers\Api\V1\Wallet;

use App\Enums\FundingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Wallet\CalculateDepositFeeRequest;
use App\Http\Requests\Api\V1\Wallet\InitiateDepositRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Customer;
use App\Models\CustomerVirtualAccount;
use App\Models\FundingRecord;
use App\Models\TransactionFeeTier;
use App\Services\Wallet\DedicatedAccountService;
use App\Services\Wallet\TransactionFeeService;
use App\Services\Wallet\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DepositController extends Controller
{
    /**
     * Create a new DepositController instance.
     */
    public function __construct(
        protected WalletService $walletService,
        protected TransactionFeeService $feeService,
        protected DedicatedAccountService $dedicatedAccountService,
    ) {}

    /**
     * List all active deposit fee tiers with formatted display contexts.
     * GET /api/v1/wallet/deposit/tiers
     */
    public function tiers(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer');

        $tiers = $this->feeService->getTiers('deposit');

        $formattedTiers = $tiers->map(function (TransactionFeeTier $tier): array {
            $minNgn = number_format($tier->min_amount / 100, 2);
            $maxNgn = $tier->max_amount !== null ? number_format($tier->max_amount / 100, 2) : null;
            $feeNgn = number_format($tier->fee / 100, 2);

            $rangeText = $tier->max_amount !== null
                ? "₦{$minNgn} – ₦{$maxNgn}"
                : "Above ₦{$minNgn}";

            return [
                'id' => $tier->id,
                'name' => $tier->name,
                'min_amount_kobo' => $tier->min_amount,
                'min_amount_ngn' => $tier->min_amount / 100,
                'max_amount_kobo' => $tier->max_amount,
                'max_amount_ngn' => $tier->max_amount !== null ? $tier->max_amount / 100 : null,
                'fee_kobo' => $tier->fee,
                'fee_ngn' => $tier->fee / 100,
                'fee_formatted' => "₦{$feeNgn}",
                'range_formatted' => $rangeText,
                'description' => "Transactions {$rangeText} incur a fee of ₦{$feeNgn}.",
            ];
        })->values();

        // Check if customer already has a dedicated virtual account
        /** @var CustomerVirtualAccount|null $virtualAccount */
        $virtualAccount = CustomerVirtualAccount::where('customer_id', $customer->id)
            ->where('status', 'active')
            ->first();

        $accountSummary = null;
        if ($virtualAccount) {
            $accountSummary = [
                'bank_name' => $virtualAccount->bank_name,
                'account_number' => $virtualAccount->account_number,
                'account_name' => $virtualAccount->account_name,
                'status' => $virtualAccount->status,
            ];
        }

        return ApiResponse::success(
            data: [
                'tiers' => $formattedTiers,
                'fee_context' => [
                    'title' => 'Deposit Processing Fees',
                    'below_1000' => '₦0 – ₦999: ₦20 fee',
                    'tier_1000_10000' => '₦1,000 – ₦10,000: ₦75 fee',
                    'tier_10000_50000' => '₦10,001 – ₦50,000: ₦155 fee',
                    'tier_50000_100000' => '₦50,001 – ₦100,000: ₦350 fee',
                    'above_100000' => 'Above ₦100,000: ₦500 flat fee',
                    'notice' => 'Fees apply to both in-app deposit requests and direct bank transfers sent to your dedicated virtual account.',
                ],
                'dedicated_account' => $accountSummary,
                'has_dedicated_account' => $virtualAccount !== null,
            ]
        );
    }

    /**
     * Calculate fee and total payable for a given deposit amount.
     * POST /api/v1/wallet/deposit/calculate
     */
    public function calculate(CalculateDepositFeeRequest $request): JsonResponse
    {
        $amountInKobo = $request->getAmountInKobo();

        if ($amountInKobo < 10000) {
            return ApiResponse::error(
                message: 'The minimum deposit amount is ₦100.00.',
                code: 'amount_too_low',
                status: 422
            );
        }

        if ($amountInKobo > 500000000) {
            return ApiResponse::error(
                message: 'The maximum deposit amount per transaction is ₦5,000,000.00.',
                code: 'amount_too_high',
                status: 422
            );
        }

        $breakdown = $this->feeService->calculateDepositBreakdown($amountInKobo);

        return ApiResponse::success(
            data: [
                'requested_amount_kobo' => $breakdown['requested_amount'],
                'requested_amount_ngn' => $breakdown['requested_amount'] / 100,
                'requested_amount_formatted' => $breakdown['requested_amount_formatted'],
                'fee_kobo' => $breakdown['fee'],
                'fee_ngn' => $breakdown['fee'] / 100,
                'fee_formatted' => $breakdown['fee_formatted'],
                'total_payable_kobo' => $breakdown['total_payable'],
                'total_payable_ngn' => $breakdown['total_payable'] / 100,
                'total_payable_formatted' => $breakdown['total_payable_formatted'],
                'tier_name' => $breakdown['tier_name'],
                'summary' => "Deposit {$breakdown['requested_amount_formatted']} + Fee {$breakdown['fee_formatted']} = Total Payable {$breakdown['total_payable_formatted']}.",
            ]
        );
    }

    /**
     * Initiate a deposit intent and retrieve dedicated virtual account for transfer.
     * POST /api/v1/wallet/deposit/initiate
     */
    public function initiate(InitiateDepositRequest $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer');

        $amountInKobo = $request->getAmountInKobo();

        if ($amountInKobo < 10000) {
            return ApiResponse::error(
                message: 'The minimum deposit amount is ₦100.00.',
                code: 'amount_too_low',
                status: 422
            );
        }

        if ($amountInKobo > 500000000) {
            return ApiResponse::error(
                message: 'The maximum deposit amount per transaction is ₦5,000,000.00.',
                code: 'amount_too_high',
                status: 422
            );
        }

        $wallet = $this->walletService->getOrCreateWallet($customer);
        $breakdown = $this->feeService->calculateDepositBreakdown($amountInKobo);

        // Fetch or create dedicated virtual account
        $accountResult = $this->dedicatedAccountService->generateAccount($customer);

        if ($accountResult['status'] !== 'success' || empty($accountResult['data'])) {
            return ApiResponse::error(
                message: $accountResult['message'] ?? 'Unable to retrieve virtual account for deposit. Please try again shortly.',
                code: $accountResult['code'] ?? 'virtual_account_unavailable',
                status: 503
            );
        }

        /** @var array<string, mixed> $accountData */
        $accountData = $accountResult['data'];
        $reference = 'KORAK-DEP-'.strtoupper(Str::random(12));

        $fundingRecord = FundingRecord::create([
            'customer_id' => $customer->id,
            'wallet_id' => $wallet->id,
            'provider' => 'finitypay',
            'reference' => $reference,
            'amount' => $amountInKobo,
            'gross_amount' => $breakdown['total_payable'],
            'fee' => $breakdown['fee'],
            'currency' => $wallet->currency,
            'status' => FundingStatus::PENDING,
            'metadata' => [
                'type' => 'dedicated_virtual_account_deposit',
                'requested_amount' => $amountInKobo,
                'fee' => $breakdown['fee'],
                'total_payable' => $breakdown['total_payable'],
                'tier_name' => $breakdown['tier_name'],
                'virtual_account_number' => $accountData['account_number'] ?? null,
                'virtual_bank_name' => $accountData['bank_name'] ?? null,
            ],
        ]);

        return ApiResponse::success(
            data: [
                'reference' => $fundingRecord->reference,
                'status' => $fundingRecord->status->value,
                'breakdown' => [
                    'requested_amount_kobo' => $breakdown['requested_amount'],
                    'requested_amount_ngn' => $breakdown['requested_amount'] / 100,
                    'requested_amount_formatted' => $breakdown['requested_amount_formatted'],
                    'fee_kobo' => $breakdown['fee'],
                    'fee_ngn' => $breakdown['fee'] / 100,
                    'fee_formatted' => $breakdown['fee_formatted'],
                    'total_payable_kobo' => $breakdown['total_payable'],
                    'total_payable_ngn' => $breakdown['total_payable'] / 100,
                    'total_payable_formatted' => $breakdown['total_payable_formatted'],
                    'tier_name' => $breakdown['tier_name'],
                ],
                'virtual_account' => [
                    'bank_name' => $accountData['bank_name'] ?? 'Paga',
                    'account_number' => $accountData['account_number'] ?? '',
                    'account_name' => $accountData['account_name'] ?? '',
                ],
                'instructions' => [
                    'headline' => "Transfer {$breakdown['total_payable_formatted']} to your dedicated account",
                    'steps' => [
                        'Open your bank app or internet banking.',
                        "Send {$breakdown['total_payable_formatted']} to {$accountData['bank_name']} - {$accountData['account_number']}.",
                        "Your wallet balance will be credited with {$breakdown['requested_amount_formatted']} instantly once confirmed.",
                    ],
                ],
            ],
            status: 201
        );
    }
}
