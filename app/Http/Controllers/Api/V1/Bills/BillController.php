<?php

namespace App\Http\Controllers\Api\V1\Bills;

use App\Exceptions\InsufficientBalanceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Bills\PayBillRequest;
use App\Http\Requests\Api\V1\Bills\ValidateBillRequest;
use App\Http\Resources\Api\V1\BillOrderResource;
use App\Http\Responses\ApiResponse;
use App\Models\BillOrder;
use App\Models\Customer;
use App\Models\WebsiteService;
use App\Services\Bills\BillService;
use App\Services\Providers\FinityPayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillController extends Controller
{
    /**
     * Create a new BillController instance.
     */
    public function __construct(
        protected BillService $billService,
        protected FinityPayService $finityPayService,
    ) {}

    /**
     * Retrieve supported bill payment categories.
     * GET /api/v1/bills/categories
     */
    public function categories(): JsonResponse
    {
        $categories = $this->finityPayService->getBillCategories();

        if (isset($categories['status']) && $categories['status'] === 'error') {
            return ApiResponse::error(
                message: $categories['message'] ?? 'Unable to retrieve bill categories.',
                code: 'bill_categories_failed',
                status: 502
            );
        }

        $data = $categories['data'] ?? $categories;

        return ApiResponse::success($data);
    }

    /**
     * Fetch available bill providers and services (optionally filtered by ?category=).
     * GET /api/v1/bills/services
     */
    public function services(Request $request): JsonResponse
    {
        $category = $request->query('category');
        if (is_string($category) && $category !== '' && ! WebsiteService::isServiceActive($category)) {
            $svc = WebsiteService::findByCode($category);

            return ApiResponse::error(
                message: $svc?->maintenance_message ?: "The {$category} service is currently unavailable.",
                code: 'service_unavailable',
                status: 503
            );
        }

        $services = $this->finityPayService->getBillServices(is_string($category) ? $category : null);

        if (isset($services['status']) && $services['status'] === 'error') {
            return ApiResponse::error(
                message: $services['message'] ?? 'Unable to retrieve bill services.',
                code: 'bill_services_failed',
                status: 502
            );
        }

        $data = $services['data'] ?? $services;

        return ApiResponse::success($data);
    }

    /**
     * Fetch live data bundle packages for a specific service code.
     * GET /api/v1/bills/data-bundles?service_code=MTN_DATA
     */
    public function dataBundles(Request $request): JsonResponse
    {
        if (! WebsiteService::isServiceActive('data')) {
            $svc = WebsiteService::findByCode('data');

            return ApiResponse::error(
                message: $svc?->maintenance_message ?: 'Data bundle purchase is currently unavailable.',
                code: 'service_unavailable',
                status: 503
            );
        }

        $serviceCode = (string) $request->query('service_code');

        if ($serviceCode === '') {
            return ApiResponse::error(
                message: 'The service_code query parameter is required.',
                code: 'validation_failed',
                status: 422
            );
        }

        $bundles = $this->finityPayService->getDataBundles($serviceCode);

        if (isset($bundles['status']) && $bundles['status'] === 'error') {
            return ApiResponse::error(
                message: $bundles['message'] ?? 'Unable to retrieve data bundles.',
                code: 'data_bundles_failed',
                status: 502
            );
        }

        $data = $bundles['data'] ?? $bundles;

        return ApiResponse::success($data);
    }

    /**
     * Verify electricity meter or cable TV smartcard customer details.
     * POST /api/v1/bills/validate
     */
    public function validateCustomer(ValidateBillRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $result = $this->finityPayService->validateBillCustomer(
            serviceCode: $validated['service_code'],
            customerIdentifier: $validated['customer_identifier']
        );

        if (isset($result['status']) && $result['status'] === 'error') {
            return ApiResponse::error(
                message: $result['message'] ?? 'Could not validate customer details.',
                code: 'customer_validation_failed',
                status: 422
            );
        }

        $data = $result['data'] ?? $result;

        return ApiResponse::success($data);
    }

    /**
     * Purchase airtime, mobile data, cable TV, or utility token.
     * POST /api/v1/bills/pay
     */
    public function pay(PayBillRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $category = strtolower((string) ($validated['category'] ?? ''));

        if ($category !== '' && ! WebsiteService::isServiceActive($category)) {
            $svc = WebsiteService::findByCode($category);

            return ApiResponse::error(
                message: $svc?->maintenance_message ?: "The {$category} service is currently unavailable. Please check back later.",
                code: 'service_unavailable',
                status: 503
            );
        }

        /** @var Customer $customer */
        $customer = $request->user('customer');

        $idempotencyKey = $request->header('Idempotency-Key');

        try {
            $billOrder = $this->billService->purchaseBill(
                customer: $customer,
                data: $validated,
                idempotencyKey: is_string($idempotencyKey) ? $idempotencyKey : null
            );

            return (new BillOrderResource($billOrder))
                ->response()
                ->setStatusCode(201);
        } catch (InsufficientBalanceException $e) {
            return ApiResponse::error(
                message: 'Insufficient wallet balance for this purchase.',
                code: 'insufficient_balance',
                status: 422
            );
        }
    }

    /**
     * Retrieve paginated history of customer's bill purchases.
     * GET /api/v1/bills/history
     */
    public function history(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer');

        $query = BillOrder::where('customer_id', $customer->id)
            ->latest('id');

        if ($request->has('category')) {
            $query->where('category', (string) $request->query('category'));
        }

        if ($request->has('status')) {
            $query->where('status', (string) $request->query('status'));
        }

        $perPage = min(max(1, (int) $request->query('per_page', 15)), 100);
        $paginator = $query->paginate($perPage);

        $transformed = $paginator->through(
            fn (BillOrder $order): array => (new BillOrderResource($order))->resolve($request)
        );

        return ApiResponse::paginated($transformed);
    }

    /**
     * Fetch status and details of a single bill purchase by reference or ULID.
     * GET /api/v1/bills/{reference}
     */
    public function show(string $reference, Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer');

        /** @var BillOrder|null $billOrder */
        $billOrder = BillOrder::where('customer_id', $customer->id)
            ->where(function ($q) use ($reference) {
                $q->where('reference', $reference)
                    ->orWhere('ulid', $reference);
            })
            ->first();

        if (! $billOrder) {
            return ApiResponse::error(
                message: 'Bill transaction not found.',
                code: 'bill_not_found',
                status: 404
            );
        }

        // If processing, query live status from provider
        if ($billOrder->status === 'processing') {
            $providerDetails = $this->finityPayService->getBillByReference($billOrder->reference);
            if (isset($providerDetails['status']) && $providerDetails['status'] === 'success') {
                $data = $providerDetails['data'] ?? [];
                $provStatus = strtolower((string) ($data['status'] ?? ''));
                if ($provStatus === 'success') {
                    $billOrder->update([
                        'status' => 'completed',
                        'token' => $data['token'] ?? $billOrder->token,
                        'provider_reference' => $data['provider_reference'] ?? $billOrder->provider_reference,
                        'processed_at' => now(),
                    ]);
                }
            }
        }

        return (new BillOrderResource($billOrder))->response();
    }
}
