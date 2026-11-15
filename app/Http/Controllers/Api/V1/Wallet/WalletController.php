<?php

namespace App\Http\Controllers\Api\V1\Wallet;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\LedgerEntryResource;
use App\Http\Resources\Api\V1\WalletResource;
use App\Http\Responses\ApiResponse;
use App\Models\Customer;
use App\Models\LedgerEntry;
use App\Services\Wallet\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected WalletService $walletService,
    ) {}

    /**
     * Get the authenticated customer's wallet.
     *
     * Per Doc 14 §1 & Roadmap B13.1.
     */
    public function show(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer');

        $wallet = $this->walletService->getOrCreateWallet($customer);

        return ApiResponse::success(new WalletResource($wallet));
    }

    /**
     * Get paginated transaction history for the authenticated customer's wallet.
     *
     * Per Doc 14 §8 & Roadmap B13.4.
     */
    public function transactions(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer');

        $wallet = $this->walletService->getOrCreateWallet($customer);

        $query = $wallet->ledgerEntries();

        // Filter by direction (credit / debit)
        if ($request->filled('direction')) {
            $query->where('direction', $request->input('direction'));
        } elseif ($request->filled('filter.direction')) {
            $query->where('direction', $request->input('filter.direction'));
        }

        // Filter by type (funding, order_debit, refund, manual_adjustment)
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        } elseif ($request->filled('filter.type')) {
            $query->where('type', $request->input('filter.type'));
        }

        // Search by reference or description
        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($q) use ($search): void {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Sorting: default newest first
        $query->latest('created_at');

        $perPage = min(max((int) $request->input('per_page', 15), 1), 50);

        $paginator = $query->paginate($perPage);

        $transformed = $paginator->through(
            fn (LedgerEntry $entry): array => (new LedgerEntryResource($entry))->resolve($request)
        );

        return ApiResponse::paginated($transformed);
    }
}
