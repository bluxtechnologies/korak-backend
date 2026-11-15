<?php

namespace App\Http\Controllers\Api\V1\Wallet;

use App\Enums\FundingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Wallet\FundWalletRequest;
use App\Http\Resources\Api\V1\FundingRecordResource;
use App\Http\Responses\ApiResponse;
use App\Models\Customer;
use App\Models\FundingRecord;
use App\Services\Providers\FinityPayService;
use App\Services\Wallet\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FundingController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected WalletService $walletService,
        protected FinityPayService $finityPayService,
    ) {}

    /**
     * Initiate a new wallet funding session.
     *
     * Per Doc 14 §4 and Roadmap B14.1.
     */
    public function initiate(FundWalletRequest $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer');

        $wallet = $this->walletService->getOrCreateWallet($customer);
        $amountInKobo = (int) $request->input('amount');
        $reference = 'KORAK-FUND-'.strtoupper(Str::random(12));

        $fundingRecord = FundingRecord::create([
            'customer_id' => $customer->id,
            'wallet_id' => $wallet->id,
            'provider' => 'finitypay',
            'reference' => $reference,
            'amount' => $amountInKobo,
            'currency' => $wallet->currency,
            'status' => FundingStatus::PENDING,
        ]);

        $redirectUrl = $request->input('redirect_url');
        $res = $this->finityPayService->initiatePayment(
            customer: $customer,
            amountInKobo: $amountInKobo,
            reference: $reference,
            redirectUrl: is_string($redirectUrl) ? $redirectUrl : null
        );

        if ($res['status'] !== 'success') {
            $fundingRecord->update([
                'status' => FundingStatus::FAILED,
            ]);

            return ApiResponse::error(
                message: $res['message'] ?? 'Failed to initialize payment gateway.',
                code: 'payment_initiation_failed',
                status: 502
            );
        }

        $fundingRecord->update([
            'checkout_url' => $res['checkout_url'] ?? null,
            'provider_reference' => $res['provider_reference'] ?? null,
        ]);

        return ApiResponse::success(
            data: new FundingRecordResource($fundingRecord->fresh()),
            status: 201
        );
    }

    /**
     * Check the status of a specific funding record.
     */
    public function show(Request $request, string $reference): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer');

        /** @var FundingRecord|null $fundingRecord */
        $fundingRecord = FundingRecord::where('customer_id', $customer->id)
            ->where(function ($q) use ($reference): void {
                $q->where('reference', $reference)
                    ->orWhere('ulid', $reference);
            })
            ->first();

        if (! $fundingRecord) {
            return ApiResponse::error(
                message: 'Funding record not found.',
                code: 'funding_record_not_found',
                status: 404
            );
        }

        return ApiResponse::success(new FundingRecordResource($fundingRecord));
    }
}
