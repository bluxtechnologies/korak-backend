<?php

namespace App\Services\Providers;

use App\Enums\FundingStatus;
use App\Enums\LedgerEntryType;
use App\Models\BillOrder;
use App\Models\Customer;
use App\Models\CustomerVirtualAccount;
use App\Models\FundingRecord;
use App\Models\FundingWebhookEvent;
use App\Models\Wallet;
use App\Notifications\WalletFundedNotification;
use App\Services\Wallet\WalletService;
use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FinityPayService
{
    /**
     * Create a new FinityPay provider service instance.
     */
    public function __construct(
        protected WalletService $walletService,
    ) {}

    /**
     * Get the active FinityPay base URL.
     */
    public function getBaseUrl(): string
    {
        return rtrim((string) config('services.finitypay.base_url', 'https://api.cofinance.ng/v1'), '/');
    }

    /**
     * Get the secret key from configuration.
     */
    public function getSecretKey(): ?string
    {
        $key = config('services.finitypay.secret_key');

        return is_string($key) && $key !== '' ? $key : null;
    }

    /**
     * Get the webhook secret from configuration.
     */
    public function getWebhookSecret(): ?string
    {
        $secret = config('services.finitypay.webhook_secret');

        return is_string($secret) && $secret !== '' ? $secret : null;
    }

    /**
     * Build standard headers for FinityPay / CoFinance API calls.
     *
     * @return array<string, string>
     */
    public function buildHeaders(): array
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        $secretKey = $this->getSecretKey();
        if ($secretKey) {
            $headers['Authorization'] = 'Bearer '.$secretKey;
        }

        $businessId = config('services.finitypay.business_id');
        if (is_string($businessId) && $businessId !== '') {
            $headers['x-business-id'] = $businessId;
        }

        return $headers;
    }

    // =========================================================================
    // Bills & VTU API Methods
    // =========================================================================

    /**
     * Retrieve supported bill payment categories.
     * GET /v1/bills/categories
     *
     * @return array<string, mixed>
     */
    public function getBillCategories(): array
    {
        $baseUrl = $this->getBaseUrl();
        $headers = $this->buildHeaders();
        $timeout = (int) config('services.finitypay.timeout', 25);

        try {
            $response = Http::timeout($timeout)->withHeaders($headers)->get($baseUrl.'/bills/categories');

            if ($response->successful()) {
                return $response->json() ?? [];
            }

            return [
                'status' => 'error',
                'message' => 'Failed to retrieve bill categories.',
            ];
        } catch (Exception $e) {
            Log::error('FinityPay getBillCategories exception', ['message' => $e->getMessage()]);

            return [
                'status' => 'error',
                'message' => 'Could not connect to bill services provider.',
            ];
        }
    }

    /**
     * Fetch available bill providers and pre-configured data packages.
     * GET /v1/bills/services?category={category}
     *
     * @return array<string, mixed>
     */
    public function getBillServices(?string $category = null): array
    {
        $baseUrl = $this->getBaseUrl();
        $headers = $this->buildHeaders();
        $timeout = (int) config('services.finitypay.timeout', 25);

        try {
            $query = $category ? ['category' => $category] : [];
            $response = Http::timeout($timeout)->withHeaders($headers)->get($baseUrl.'/bills/services', $query);

            if ($response->successful()) {
                return $response->json() ?? [];
            }

            return [
                'status' => 'error',
                'message' => 'Failed to retrieve bill services.',
            ];
        } catch (Exception $e) {
            Log::error('FinityPay getBillServices exception', ['message' => $e->getMessage()]);

            return [
                'status' => 'error',
                'message' => 'Could not connect to bill services provider.',
            ];
        }
    }

    /**
     * Fetch live data bundle packages by service code.
     * GET /v1/bills/data-bundles?service_code={serviceCode}
     *
     * @return array<string, mixed>
     */
    public function getDataBundles(string $serviceCode): array
    {
        $baseUrl = $this->getBaseUrl();
        $headers = $this->buildHeaders();
        $timeout = (int) config('services.finitypay.timeout', 25);

        try {
            $response = Http::timeout($timeout)->withHeaders($headers)->get($baseUrl.'/bills/data-bundles', [
                'service_code' => $serviceCode,
            ]);

            if ($response->successful()) {
                return $response->json() ?? [];
            }

            return [
                'status' => 'error',
                'message' => 'Failed to retrieve data bundles.',
            ];
        } catch (Exception $e) {
            Log::error('FinityPay getDataBundles exception', ['message' => $e->getMessage()]);

            return [
                'status' => 'error',
                'message' => 'Could not connect to data bundles provider.',
            ];
        }
    }

    /**
     * Verify electricity meter numbers or Cable TV smartcard numbers before payment.
     * POST /v1/bills/validate
     *
     * @return array<string, mixed>
     */
    public function validateBillCustomer(string $serviceCode, string $customerIdentifier): array
    {
        $baseUrl = $this->getBaseUrl();
        $headers = $this->buildHeaders();
        $timeout = (int) config('services.finitypay.timeout', 25);

        try {
            $response = Http::timeout($timeout)->withHeaders($headers)->post($baseUrl.'/bills/validate', [
                'service_code' => $serviceCode,
                'customer_identifier' => $customerIdentifier,
            ]);

            if ($response->successful()) {
                return $response->json() ?? [];
            }

            $body = $response->json();

            return [
                'status' => 'error',
                'message' => $body['message'] ?? 'Customer details could not be validated.',
            ];
        } catch (Exception $e) {
            Log::error('FinityPay validateBillCustomer exception', ['message' => $e->getMessage()]);

            return [
                'status' => 'error',
                'message' => 'Could not connect to customer validation provider.',
            ];
        }
    }

    /**
     * Purchase airtime, mobile data, cable TV, or electricity token.
     * POST /v1/bills/pay
     *
     * @param  array{service_code: string, customer_identifier: string, amount: float, package_code?: ?string}  $params
     * @return array<string, mixed>
     */
    public function payBill(array $params): array
    {
        $baseUrl = $this->getBaseUrl();
        $headers = $this->buildHeaders();
        $timeout = (int) config('services.finitypay.timeout', 45);

        $payload = [
            'service_code' => $params['service_code'],
            'customer_identifier' => $params['customer_identifier'],
            'amount' => (float) $params['amount'],
        ];

        if (! empty($params['package_code'])) {
            $payload['package_code'] = $params['package_code'];
        }

        try {
            $response = Http::timeout($timeout)->withHeaders($headers)->post($baseUrl.'/bills/pay', $payload);

            if ($response->successful()) {
                return $response->json() ?? [];
            }

            $body = $response->json();

            return [
                'status' => 'error',
                'message' => $body['message'] ?? 'Bill payment failed with provider.',
                'error_data' => $body,
            ];
        } catch (Exception $e) {
            Log::error('FinityPay payBill exception', ['message' => $e->getMessage()]);

            return [
                'status' => 'error',
                'message' => 'Connection timeout during bill payment.',
            ];
        }
    }

    /**
     * Fetch single bill payment status by reference.
     * GET /v1/bills/{reference}
     *
     * @return array<string, mixed>
     */
    public function getBillByReference(string $reference): array
    {
        $baseUrl = $this->getBaseUrl();
        $headers = $this->buildHeaders();
        $timeout = (int) config('services.finitypay.timeout', 25);

        try {
            $response = Http::timeout($timeout)->withHeaders($headers)->get($baseUrl.'/bills/'.$reference);

            if ($response->successful()) {
                return $response->json() ?? [];
            }

            return [
                'status' => 'error',
                'message' => 'Bill payment details could not be retrieved.',
            ];
        } catch (Exception $e) {
            Log::error('FinityPay getBillByReference exception', ['message' => $e->getMessage()]);

            return [
                'status' => 'error',
                'message' => 'Could not connect to provider.',
            ];
        }
    }

    /**
     * Retrieve paginated history of all bill payments and VTU transactions.
     * GET /v1/bills/history
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function getBillHistory(array $params = []): array
    {
        $baseUrl = $this->getBaseUrl();
        $headers = $this->buildHeaders();
        $timeout = (int) config('services.finitypay.timeout', 25);

        try {
            $response = Http::timeout($timeout)->withHeaders($headers)->get($baseUrl.'/bills/history', $params);

            if ($response->successful()) {
                return $response->json() ?? [];
            }

            return [
                'status' => 'error',
                'message' => 'Bill history could not be retrieved.',
            ];
        } catch (Exception $e) {
            Log::error('FinityPay getBillHistory exception', ['message' => $e->getMessage()]);

            return [
                'status' => 'error',
                'message' => 'Could not connect to provider.',
            ];
        }
    }

    // =========================================================================
    // Wallet Funding & Payment Checkout Methods
    // =========================================================================

    /**
     * Initiate a payment session on the gateway.
     *
     * @return array{status: string, reference?: string, checkout_url?: string, provider_reference?: ?string, message?: string}
     */
    public function initiatePayment(
        Customer $customer,
        int $amountInKobo,
        string $reference,
        ?string $redirectUrl = null
    ): array {
        $secretKey = $this->getSecretKey();
        $baseUrl = $this->getBaseUrl();

        if (! $secretKey) {
            Log::error('FinityPay API secret key is not configured.');

            return [
                'status' => 'error',
                'message' => 'Payment gateway credentials are not configured.',
            ];
        }

        $amountInNgn = round($amountInKobo / 100, 2);
        $frontendUrl = (string) config('app.frontend_url', 'http://localhost:3000');
        $callbackUrl = $redirectUrl ?: rtrim($frontendUrl, '/').'/wallet';

        $payload = [
            'amount' => $amountInNgn,
            'customer_email' => $customer->email,
            'customer_name' => $customer->username,
            'reference' => $reference,
            'redirect_url' => $callbackUrl,
        ];

        try {
            $headers = $this->buildHeaders();
            $timeout = (int) config('services.finitypay.timeout', 25);

            /** @var Response $response */
            $response = Http::timeout($timeout)
                ->withHeaders($headers)
                ->post($baseUrl.'/payments', $payload);

            if ($response->successful()) {
                /** @var array<string, mixed> $body */
                $body = $response->json() ?? [];
                /** @var array<string, mixed> $data */
                $data = isset($body['data']) && is_array($body['data']) ? $body['data'] : $body;

                $checkoutUrl = isset($data['payment_url']) && is_string($data['payment_url'])
                    ? $data['payment_url']
                    : (isset($data['checkout_url']) && is_string($data['checkout_url'])
                        ? $data['checkout_url']
                        : $baseUrl.'/pay/'.$reference);

                $providerRef = isset($data['reference']) && is_string($data['reference'])
                    ? $data['reference']
                    : null;

                return [
                    'status' => 'success',
                    'reference' => $reference,
                    'checkout_url' => $checkoutUrl,
                    'provider_reference' => $providerRef,
                ];
            }

            Log::warning('FinityPay payment initiation failed', [
                'status' => $response->status(),
                'reference' => $reference,
            ]);

            return [
                'status' => 'error',
                'message' => 'Failed to initialize payment with payment provider.',
            ];
        } catch (Exception $e) {
            Log::error('FinityPay connection exception', [
                'message' => $e->getMessage(),
                'reference' => $reference,
            ]);

            return [
                'status' => 'error',
                'message' => 'Could not connect to payment provider.',
            ];
        }
    }

    /**
     * Register customer on FinityPay provider.
     *
     * @return array{status: string, data?: array<string, mixed>, message?: string}
     */
    public function createCustomer(Customer $customer, ?string $phone = null): array
    {
        $secretKey = $this->getSecretKey();
        $baseUrl = $this->getBaseUrl();

        if (! $secretKey) {
            return [
                'status' => 'error',
                'message' => 'Payment gateway credentials are not configured.',
            ];
        }

        $customerPhone = $phone ?: $customer->phone ?: '08000000000';
        $fullName = trim("{$customer->first_name} {$customer->last_name}") ?: $customer->username;

        $payload = [
            'name' => $fullName,
            'email' => $customer->email,
            'phone' => $customerPhone,
        ];

        try {
            $headers = $this->buildHeaders();
            $timeout = (int) config('services.finitypay.timeout', 25);

            /** @var Response $response */
            $response = Http::timeout($timeout)
                ->withHeaders($headers)
                ->post($baseUrl.'/customers', $payload);

            if ($response->successful()) {
                /** @var array<string, mixed> $body */
                $body = $response->json() ?? [];
                /** @var array<string, mixed> $data */
                $data = isset($body['data']) && is_array($body['data']) ? $body['data'] : $body;

                $providerCustomerId = (string) ($data['id'] ?? $data['uuid'] ?? '');
                if ($providerCustomerId !== '') {
                    $customer->update(['provider_customer_id' => $providerCustomerId]);
                }

                return [
                    'status' => 'success',
                    'data' => $data,
                ];
            }

            return [
                'status' => 'error',
                'message' => $response->json('message') ?? 'Failed to register customer with payment provider.',
            ];
        } catch (Exception $e) {
            Log::error('FinityPay createCustomer exception', ['error' => $e->getMessage()]);

            return [
                'status' => 'error',
                'message' => 'Could not connect to payment provider.',
            ];
        }
    }

    /**
     * Create or retrieve a dedicated virtual account for customer deposit.
     *
     * @return array{status: string, data?: array<string, mixed>, message?: string}
     */
    public function createVirtualAccount(Customer $customer, Wallet $wallet, ?string $phone = null): array
    {
        // If customer already has an active virtual account, return it immediately
        /** @var CustomerVirtualAccount|null $existing */
        $existing = CustomerVirtualAccount::where('customer_id', $customer->id)
            ->where('status', 'active')
            ->first();

        if ($existing) {
            return [
                'status' => 'success',
                'data' => [
                    'id' => $existing->ulid,
                    'bank_name' => $existing->bank_name,
                    'bank_code' => $existing->bank_code,
                    'account_number' => $existing->account_number,
                    'account_name' => $existing->account_name,
                    'account_reference' => $existing->account_reference,
                    'status' => $existing->status,
                ],
            ];
        }

        $secretKey = $this->getSecretKey();
        $baseUrl = $this->getBaseUrl();

        if (! $secretKey) {
            return [
                'status' => 'error',
                'message' => 'Payment gateway credentials are not configured.',
            ];
        }

        // Ensure customer is registered on provider if not already
        if (! $customer->provider_customer_id) {
            $customerRes = $this->createCustomer($customer, $phone);
            if ($customerRes['status'] !== 'success') {
                return $customerRes;
            }
        }

        $customerPhone = $phone ?: $customer->phone ?: '08000000000';
        $fullName = trim("{$customer->first_name} {$customer->last_name}") ?: $customer->username;

        $payload = [
            'customer_id' => $customer->provider_customer_id,
            'customer_email' => $customer->email,
            'customer_name' => $fullName,
            'customer_phone' => $customerPhone,
        ];

        try {
            $headers = $this->buildHeaders();
            $timeout = (int) config('services.finitypay.timeout', 25);

            /** @var Response $response */
            $response = Http::timeout($timeout)
                ->withHeaders($headers)
                ->post($baseUrl.'/virtual-accounts', $payload);

            if ($response->successful()) {
                /** @var array<string, mixed> $body */
                $body = $response->json() ?? [];
                /** @var array<string, mixed> $data */
                $data = isset($body['data']) && is_array($body['data']) ? $body['data'] : $body;

                /** @var CustomerVirtualAccount $account */
                $account = CustomerVirtualAccount::create([
                    'customer_id' => $customer->id,
                    'wallet_id' => $wallet->id,
                    'provider' => 'finitypay',
                    'provider_account_id' => isset($data['id']) ? (string) $data['id'] : (isset($data['account_id']) ? (string) $data['account_id'] : null),
                    'account_reference' => (string) ($data['reference'] ?? 'va_'.uniqid()),
                    'bank_name' => (string) ($data['bank_name'] ?? 'Wema Bank'),
                    'bank_code' => isset($data['bank_code']) ? (string) $data['bank_code'] : null,
                    'account_number' => (string) ($data['account_number'] ?? ''),
                    'account_name' => (string) ($data['account_name'] ?? "COFINANCE / {$fullName}"),
                    'status' => 'active',
                    'metadata' => $data,
                ]);

                return [
                    'status' => 'success',
                    'data' => [
                        'id' => $account->ulid,
                        'bank_name' => $account->bank_name,
                        'bank_code' => $account->bank_code,
                        'account_number' => $account->account_number,
                        'account_name' => $account->account_name,
                        'account_reference' => $account->account_reference,
                        'status' => $account->status,
                    ],
                ];
            }

            return [
                'status' => 'error',
                'message' => $response->json('message') ?? 'Failed to generate dedicated virtual account.',
            ];
        } catch (Exception $e) {
            Log::error('FinityPay createVirtualAccount exception', ['error' => $e->getMessage()]);

            return [
                'status' => 'error',
                'message' => 'Could not connect to payment provider.',
            ];
        }
    }

    /**
     * Verify payment status on the gateway.
     *
     * @return array{status: string, data?: array<string, mixed>, message?: string}
     */
    public function verifyPayment(string $reference): array
    {
        $secretKey = $this->getSecretKey();
        $baseUrl = $this->getBaseUrl();

        if (! $secretKey) {
            return [
                'status' => 'error',
                'message' => 'Payment gateway credentials are not configured.',
            ];
        }

        try {
            $headers = $this->buildHeaders();
            $timeout = (int) config('services.finitypay.timeout', 25);

            /** @var Response $response */
            $response = Http::timeout($timeout)
                ->withHeaders($headers)
                ->get($baseUrl.'/payments/verify/'.$reference);

            if ($response->successful()) {
                /** @var array<string, mixed> $body */
                $body = $response->json() ?? [];
                /** @var array<string, mixed> $data */
                $data = isset($body['data']) && is_array($body['data']) ? $body['data'] : $body;

                return [
                    'status' => 'success',
                    'data' => $data,
                ];
            }

            return [
                'status' => 'error',
                'message' => 'Payment verification returned error status.',
            ];
        } catch (Exception $e) {
            Log::error('FinityPay verify exception', ['error' => $e->getMessage()]);

            return [
                'status' => 'error',
                'message' => 'Payment verification connection failed.',
            ];
        }
    }

    // =========================================================================
    // Webhook Signature & Event Processing
    // =========================================================================

    /**
     * Validate FinityPay / CoFinance webhook signature.
     * Supports HMAC SHA512 (per documentation) and HMAC SHA256 (per dashboard UI),
     * hex and base64 encodings, and timestamped format (t=...,v1=...).
     */
    public function verifyWebhookSignature(string $rawContent, ?string $signature, ?string $headerTimestamp = null): bool
    {
        if (config('services.finitypay.skip_webhook_verify', false)) {
            Log::info('FinityPay webhook signature verification bypassed via FINITYPAY_SKIP_WEBHOOK_VERIFY=true');

            return true;
        }

        $secret = $this->getWebhookSecret();
        if (! $secret || ! $signature) {
            return false;
        }

        $algorithms = ['sha512', 'sha256'];

        foreach ($algorithms as $algo) {
            // 1. Direct hex match
            $computedHex = hash_hmac($algo, $rawContent, $secret);
            if (hash_equals($computedHex, $signature)) {
                return true;
            }

            // 2. Direct base64 match
            $computedB64 = base64_encode(hash_hmac($algo, $rawContent, $secret, true));
            if (hash_equals($computedB64, $signature)) {
                return true;
            }

            // 3. Timestamped format: t=...,v1=...
            $timestamp = $headerTimestamp;
            $v1Sig = null;

            if (str_contains($signature, 't=') || str_contains($signature, 'v1=')) {
                $parts = explode(',', $signature);
                foreach ($parts as $part) {
                    $pair = explode('=', trim($part), 2);
                    if (count($pair) === 2) {
                        $key = trim($pair[0]);
                        $val = trim($pair[1]);
                        if ($key === 't') {
                            $timestamp = $val;
                        } elseif ($key === 'v1') {
                            $v1Sig = $val;
                        }
                    }
                }
            } else {
                $v1Sig = $signature;
            }

            if ($v1Sig) {
                if (hash_equals($computedB64, $v1Sig) || hash_equals($computedHex, $v1Sig)) {
                    return true;
                }

                if ($timestamp) {
                    $signedPayload = $timestamp.'.'.$rawContent;
                    $tB64 = base64_encode(hash_hmac($algo, $signedPayload, $secret, true));
                    $tHex = hash_hmac($algo, $signedPayload, $secret);

                    if (hash_equals($tB64, $v1Sig) || hash_equals($tHex, $v1Sig)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Process FinityPay webhook notification with idempotency check.
     * Supports:
     * - wallet.funded: dedicated virtual account funding
     * - bill.successful: VTU recharge / bill execution success
     * - bill.failed: VTU failure & automatic wallet refund
     * - bill.pending: VTU asynchronous processing
     *
     * @param  array<string, mixed>  $payload
     */
    public function processWebhook(array $payload, string $eventReference): bool
    {
        $eventId = (string) ($payload['id'] ?? $payload['reference'] ?? $eventReference);

        /** @var FundingWebhookEvent|null $existingEvent */
        $existingEvent = FundingWebhookEvent::where('event_reference', $eventId)->first();
        if ($existingEvent && $existingEvent->status === 'processed') {
            return true;
        }

        $eventType = (string) ($payload['type'] ?? $payload['event'] ?? 'wallet.funded');

        $event = FundingWebhookEvent::firstOrCreate(
            ['event_reference' => $eventId],
            [
                'provider' => 'finitypay',
                'event_type' => $eventType,
                'payload' => $payload,
                'status' => 'received',
            ]
        );

        // Check if top-level event indicates failure
        $gatewayStatus = strtolower((string) ($payload['gateway_status'] ?? $payload['status'] ?? ''));

        // =====================================================================
        // Handle Bill Payment Events (bill.successful, bill.failed, bill.pending)
        // =====================================================================
        if (str_starts_with($eventType, 'bill.')) {
            return $this->processBillWebhook($event, $payload, $eventType);
        }

        // =====================================================================
        // Handle Wallet Funding Events (wallet.funded, payment.success)
        // =====================================================================
        return $this->processWalletFundingWebhook($event, $payload, $eventId, $gatewayStatus);
    }

    /**
     * Handle incoming wallet.funded / bank transfer webhook events.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function processWalletFundingWebhook(
        FundingWebhookEvent $event,
        array $payload,
        string $eventId,
        string $gatewayStatus
    ): bool {
        if ($gatewayStatus === 'failed' || $gatewayStatus === 'cancelled') {
            $event->update([
                'status' => 'failed',
                'failure_reason' => "Gateway reported {$gatewayStatus}",
            ]);

            return false;
        }

        /** @var array<string, mixed> $data */
        $data = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : [];
        /** @var array<string, mixed> $transaction */
        $transaction = isset($data['transaction']) && is_array($data['transaction']) ? $data['transaction'] : [];
        /** @var array<string, mixed> $account */
        $account = isset($data['account']) && is_array($data['account']) ? $data['account'] : [];
        /** @var array<string, mixed> $customerData */
        $customerData = isset($data['customer']) && is_array($data['customer']) ? $data['customer'] : [];

        $reference = (string) (
            $transaction['reference']
            ?? $account['reference']
            ?? $data['reference']
            ?? $payload['reference']
            ?? $eventId
        );

        $grossAmountNgn = (float) (
            $transaction['amount']
            ?? $transaction['net_amount']
            ?? $data['amount']
            ?? ($payload['cleared_amount'] ?? null)
            ?? ($payload['amount'] ?? 0)
        );

        $senderName = (string) (
            $customerData['name']
            ?? $account['account_name']
            ?? ($data['sender_name'] ?? null)
            ?? ($payload['sender_name'] ?? 'Bank Transfer')
        );

        $customerEmail = (string) (
            $customerData['email']
            ?? ($data['customer_email'] ?? null)
            ?? ($payload['customer_email'] ?? null)
        );

        if ($grossAmountNgn <= 0) {
            $event->update([
                'status' => 'failed',
                'failure_reason' => 'Invalid transaction amount.',
            ]);

            return false;
        }

        $amountInKobo = (int) round($grossAmountNgn * 100);

        // Match FundingRecord by reference or provider reference
        /** @var FundingRecord|null $fundingRecord */
        $fundingRecord = FundingRecord::where('reference', $reference)
            ->orWhere('provider_reference', $reference)
            ->first();

        // If not matched by reference, match by dedicated virtual account
        $accountNumber = (string) ($account['account_number'] ?? '');
        $accountRef = (string) ($account['reference'] ?? '');

        if (! $fundingRecord && ($accountNumber !== '' || $accountRef !== '')) {
            /** @var CustomerVirtualAccount|null $virtualAccount */
            $virtualAccount = CustomerVirtualAccount::where('account_number', $accountNumber)
                ->orWhere('account_reference', $accountRef)
                ->first();

            if ($virtualAccount) {
                $customer = $virtualAccount->customer;
                $wallet = $virtualAccount->wallet;
                $fundingRecord = FundingRecord::create([
                    'customer_id' => $customer->id,
                    'wallet_id' => $wallet->id,
                    'provider' => 'finitypay',
                    'reference' => 'KORAK-FUND-'.strtoupper($eventId),
                    'provider_reference' => $reference,
                    'amount' => $amountInKobo,
                    'currency' => 'NGN',
                    'status' => FundingStatus::PENDING,
                    'sender_name' => $senderName,
                ]);
            }
        }

        // If not matched by account, match by customer email
        if (! $fundingRecord && $customerEmail !== '') {
            /** @var Customer|null $customer */
            $customer = Customer::where('email', $customerEmail)->first();
            if ($customer) {
                $wallet = $this->walletService->getOrCreateWallet($customer);
                $fundingRecord = FundingRecord::create([
                    'customer_id' => $customer->id,
                    'wallet_id' => $wallet->id,
                    'provider' => 'finitypay',
                    'reference' => 'KORAK-FUND-'.strtoupper($eventId),
                    'provider_reference' => $reference,
                    'amount' => $amountInKobo,
                    'currency' => 'NGN',
                    'status' => FundingStatus::PENDING,
                    'sender_name' => $senderName,
                ]);
            }
        }

        if (! $fundingRecord) {
            $event->update([
                'status' => 'failed',
                'failure_reason' => 'Associated funding record or customer not found.',
            ]);

            return false;
        }

        if ($fundingRecord->status === FundingStatus::SUCCESSFUL) {
            $event->update([
                'status' => 'processed',
                'funding_record_id' => $fundingRecord->id,
                'processed_at' => now(),
            ]);

            return true;
        }

        // Credit the customer's wallet using the single balance-change code path
        $wallet = $fundingRecord->wallet;
        $this->walletService->credit(
            wallet: $wallet,
            amount: $amountInKobo,
            type: LedgerEntryType::FUNDING,
            description: "Wallet Funding via FinityPay [Ref: {$reference}]",
            reference: $fundingRecord->reference,
            metadata: [
                'provider' => 'finitypay',
                'event_id' => $eventId,
                'sender_name' => $senderName,
            ]
        );

        $fundingRecord->update([
            'status' => FundingStatus::SUCCESSFUL,
            'provider_reference' => $reference,
            'sender_name' => $senderName,
            'processed_at' => now(),
        ]);

        $event->update([
            'status' => 'processed',
            'funding_record_id' => $fundingRecord->id,
            'processed_at' => now(),
        ]);

        // Dispatch queued notification to the customer
        $customer = $fundingRecord->customer;
        $customer->notify(new WalletFundedNotification(
            amountInKobo: $amountInKobo,
            currency: $fundingRecord->currency,
            reference: $fundingRecord->reference
        ));

        return true;
    }

    /**
     * Handle incoming bill execution webhook events (bill.successful, bill.failed, bill.pending).
     *
     * @param  array<string, mixed>  $payload
     */
    protected function processBillWebhook(
        FundingWebhookEvent $event,
        array $payload,
        string $eventType
    ): bool {
        /** @var array<string, mixed> $data */
        $data = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : [];
        /** @var array<string, mixed> $billData */
        $billData = isset($data['bill_payment']) && is_array($data['bill_payment'])
            ? $data['bill_payment']
            : $data;

        $reference = (string) ($billData['reference'] ?? ($payload['reference'] ?? ''));
        $providerRef = (string) ($billData['provider_reference'] ?? ($billData['uuid'] ?? ''));

        /** @var BillOrder|null $billOrder */
        $billOrder = BillOrder::where('reference', $reference)
            ->orWhere('provider_reference', $providerRef)
            ->first();

        if (! $billOrder) {
            $event->update([
                'status' => 'failed',
                'failure_reason' => 'Associated bill order not found.',
            ]);

            return false;
        }

        if ($eventType === 'bill.successful') {
            $billOrder->update([
                'status' => 'completed',
                'provider_reference' => $providerRef ?: $billOrder->provider_reference,
                'token' => $billData['token'] ?? $billOrder->token,
                'processed_at' => now(),
            ]);

            $event->update([
                'status' => 'processed',
                'processed_at' => now(),
            ]);

            return true;
        }

        if ($eventType === 'bill.pending') {
            $billOrder->update([
                'status' => 'processing',
                'provider_reference' => $providerRef ?: $billOrder->provider_reference,
            ]);

            $event->update([
                'status' => 'processed',
                'processed_at' => now(),
            ]);

            return true;
        }

        if ($eventType === 'bill.failed') {
            // Process automatic refund to customer wallet per Doc 14 §3
            if ($billOrder->status !== 'refunded') {
                $wallet = $this->walletService->getOrCreateWallet($billOrder->customer);

                $this->walletService->credit(
                    wallet: $wallet,
                    amount: $billOrder->amount,
                    type: LedgerEntryType::REFUND,
                    description: "Refund: Failed {$billOrder->service_name} [{$billOrder->reference}]",
                    reference: 'REFUND-'.$billOrder->reference,
                    metadata: [
                        'bill_order_id' => $billOrder->id,
                        'reason' => 'provider_bill_failed_webhook',
                    ]
                );

                $billOrder->update([
                    'status' => 'refunded',
                    'processed_at' => now(),
                ]);
            }

            $event->update([
                'status' => 'processed',
                'processed_at' => now(),
            ]);

            return true;
        }

        return true;
    }
}
