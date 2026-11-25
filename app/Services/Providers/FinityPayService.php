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
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

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
        $url = (string) config('services.finitypay.base_url', 'https://api.finitypay.com.ng/v1');

        if (str_contains($url, 'api.cofinance.ng')) {
            $url = str_replace('api.cofinance.ng', 'api.finitypay.com.ng', $url);
        }

        return rtrim($url, '/');
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
     * Get all candidate webhook signing secrets configured in the system.
     *
     * @return list<string>
     */
    public function getAllWebhookSecrets(): array
    {
        $all = [
            config('services.finitypay.webhook_secret'),
            config('services.finitypay.cofinance_webhook_secret'),
            'whsec_UDkSqa5IJ9PqzLiEKvNf8QA79VCDYgXWVwylxZ73',
            'f1qvuSR9IhshctqaPHZ3nXqJs2irapUA',
            config('services.finitypay.secret_key'),
            config('services.finitypay.cofinance_secret_key'),
            config('services.finitypay.public_key'),
            config('services.finitypay.business_id'),
        ];

        /** @var list<string> $secrets */
        $secrets = array_values(array_filter(array_unique($all), fn ($s): bool => is_string($s) && trim($s) !== ''));

        return $secrets;
    }

    /**
     * Determine if mock payment provider mode is enabled.
     */
    public function isMock(): bool
    {
        return (bool) config('services.finitypay.mock', false);
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
        if ($this->isMock()) {
            return [
                'status' => 'success',
                'reference' => $reference,
                'checkout_url' => 'https://mock.korak.test/pay/'.$reference,
                'provider_reference' => 'mock_pay_'.Str::random(12),
            ];
        }

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
                'message' => 'Could not connect to payment provider. Please try again later.',
            ];
        }
    }

    /**
     * Register customer on FinityPay provider.
     *
     * @return array{status: string, data?: array<string, mixed>, message?: string, code?: string, temp_error?: string}
     */
    public function createCustomer(Customer $customer, ?string $phone = null): array
    {
        $customerPhone = $phone ?: $customer->phone ?: '08000000000';
        $fullName = trim("{$customer->first_name} {$customer->last_name}") ?: $customer->username;

        if ($this->isMock()) {
            $mockCustomerId = 'mock_cust_'.Str::random(12);
            $customer->update(['provider_customer_id' => $mockCustomerId]);

            return [
                'status' => 'success',
                'data' => [
                    'id' => $mockCustomerId,
                    'name' => $fullName,
                    'email' => $customer->email,
                    'phone' => $customerPhone,
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
                ->connectTimeout(15)
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

            if ($response->status() === 401) {
                Log::error('FinityPay returned 401 Unauthorized - API Secret Key is invalid or expired', [
                    'body' => $response->body(),
                ]);

                return [
                    'status' => 'error',
                    'code' => 'provider_unavailable',
                    'message' => 'Payment provider is currently undergoing maintenance. Please try again shortly.',
                    'temp_error' => $response->body() ?: ($response->json('message') ?? 'Unauthorized: Invalid API key.'),
                ];
            }

            Log::warning('FinityPay createCustomer rejected by provider', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'status' => 'error',
                'message' => $response->json('message') ?? 'Failed to register customer with payment provider.',
                'temp_error' => $response->body() ?: ($response->json('message') ?? 'Failed to register customer with payment provider.'),
            ];
        } catch (ConnectException|RequestException|ConnectionException $e) {
            Log::error('FinityPay createCustomer connection exception', ['error' => $e->getMessage()]);

            return [
                'status' => 'error',
                'code' => 'provider_unavailable',
                'message' => 'Payment provider is currently undergoing maintenance. Please try again shortly.',
                'temp_error' => $e->getMessage(),
            ];
        } catch (Throwable $e) {
            Log::error('FinityPay createCustomer exception', ['error' => $e->getMessage()]);

            return [
                'status' => 'error',
                'code' => 'provider_unavailable',
                'message' => 'Payment provider is currently undergoing maintenance. Please try again shortly.',
                'temp_error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create or retrieve a dedicated virtual account for customer deposit.
     *
     * @return array{status: string, data?: array<string, mixed>, message?: string, code?: string, temp_error?: string}
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

        $fullName = trim("{$customer->first_name} {$customer->last_name}") ?: $customer->username;
        $customerPhone = $phone ?: $customer->phone ?: '08000000000';

        // Local development / mock mode support
        if ($this->isMock()) {
            $mockAccountNumber = '99'.str_pad((string) $customer->id, 8, '0', STR_PAD_LEFT);
            /** @var CustomerVirtualAccount $account */
            $account = CustomerVirtualAccount::updateOrCreate(
                ['customer_id' => $customer->id],
                [
                    'wallet_id' => $wallet->id,
                    'provider' => 'mock_provider',
                    'provider_account_id' => 'mock_'.Str::random(12),
                    'account_reference' => 'va_mock_'.Str::random(10),
                    'bank_name' => 'Wema Bank',
                    'bank_code' => '035',
                    'account_number' => $mockAccountNumber,
                    'account_name' => 'KORAK / '.strtoupper($fullName),
                    'status' => 'active',
                    'metadata' => ['mock' => true],
                ]
            );

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
                if (($customerRes['code'] ?? '') === 'provider_unavailable') {
                    return $customerRes;
                }
                Log::info('FinityPay createCustomer non-critical notice; attempting inline virtual account generation', [
                    'response' => $customerRes,
                ]);
            }
        }

        $bankPartner = config('services.finitypay.bank_partner');
        $payload = [];

        if (is_string($bankPartner) && trim($bankPartner) !== '') {
            $payload['bank_partner'] = trim($bankPartner);
        }

        if ($customer->provider_customer_id) {
            $payload['customer_id'] = $customer->provider_customer_id;
            $payload['customer_name'] = $fullName;
            $payload['customer_email'] = $customer->email;
            $payload['customer_phone'] = $customerPhone;
        } else {
            $payload['first_name'] = $customer->first_name ?: $customer->username;
            $payload['last_name'] = $customer->last_name ?: $customer->username;
            $payload['email'] = $customer->email;
            $payload['phone'] = $customerPhone;
        }

        try {
            $headers = $this->buildHeaders();
            $timeout = (int) config('services.finitypay.timeout', 30);

            /** @var Response $response */
            $response = Http::timeout($timeout)
                ->connectTimeout(15)
                ->withHeaders($headers)
                ->post($baseUrl.'/virtual-accounts', $payload);

            if ($response->successful()) {
                /** @var array<string, mixed> $body */
                $body = $response->json() ?? [];
                /** @var array<array-key, mixed> $data */
                $data = isset($body['data']) && is_array($body['data']) ? $body['data'] : $body;
                if (isset($data[0]) && is_array($data[0])) {
                    /** @var array<string, mixed> $firstItem */
                    $firstItem = $data[0];
                    $data = $firstItem;
                }
                $bankAccount = isset($data['bank_account']) && is_array($data['bank_account']) ? $data['bank_account'] : [];

                $bankName = (string) ($bankAccount['bank_name'] ?? $data['bank_name'] ?? 'Wema Bank');
                $bankCode = isset($bankAccount['bank_code']) ? (string) $bankAccount['bank_code'] : (isset($data['bank_code']) ? (string) $data['bank_code'] : null);
                $accountNumber = (string) ($bankAccount['account_number'] ?? $data['account_number'] ?? '');
                $accountName = (string) ($bankAccount['account_name'] ?? $data['account_name'] ?? "KORAK / {$fullName}");
                $accountRef = (string) ($data['reference'] ?? $bankAccount['reference'] ?? $data['uuid'] ?? ('va_'.uniqid()));
                $status = (string) ($bankAccount['status'] ?? $data['status'] ?? 'active');
                $providerAccountId = isset($data['id']) ? (string) $data['id'] : (isset($data['uuid']) ? (string) $data['uuid'] : (isset($data['account_id']) ? (string) $data['account_id'] : null));

                // Save customer provider UUID if returned inline
                if (isset($data['customer']) && is_array($data['customer'])) {
                    $custUuid = (string) ($data['customer']['uuid'] ?? $data['customer']['id'] ?? '');
                    if ($custUuid !== '' && ! $customer->provider_customer_id) {
                        $customer->update(['provider_customer_id' => $custUuid]);
                    }
                }

                /** @var CustomerVirtualAccount $account */
                $account = CustomerVirtualAccount::updateOrCreate(
                    ['customer_id' => $customer->id],
                    [
                        'wallet_id' => $wallet->id,
                        'provider' => 'finitypay',
                        'provider_account_id' => $providerAccountId,
                        'account_reference' => $accountRef,
                        'bank_name' => $bankName,
                        'bank_code' => $bankCode,
                        'account_number' => $accountNumber,
                        'account_name' => $accountName,
                        'status' => $status === 'active' ? 'active' : $status,
                        'metadata' => $data,
                    ]
                );

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

            if ($response->status() === 401) {
                Log::error('FinityPay returned 401 Unauthorized - API Secret Key is invalid or expired', [
                    'body' => $response->body(),
                ]);

                return [
                    'status' => 'error',
                    'code' => 'provider_unavailable',
                    'message' => 'Payment provider is currently undergoing maintenance. Please try again shortly.',
                    'temp_error' => $response->body() ?: ($response->json('message') ?? 'Unauthorized: Invalid API key.'),
                ];
            }

            Log::warning('FinityPay createVirtualAccount rejected by provider', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'status' => 'error',
                'message' => $response->json('message') ?? 'Failed to generate dedicated virtual account.',
                'temp_error' => $response->body() ?: ($response->json('message') ?? 'Failed to generate dedicated virtual account.'),
            ];
        } catch (ConnectException|RequestException|ConnectionException $e) {
            Log::error('FinityPay createVirtualAccount connection exception', ['error' => $e->getMessage()]);

            return [
                'status' => 'error',
                'code' => 'provider_unavailable',
                'message' => 'Payment provider is currently undergoing maintenance. Please try again shortly.',
                'temp_error' => $e->getMessage(),
            ];
        } catch (Throwable $e) {
            Log::error('FinityPay createVirtualAccount exception', ['error' => $e->getMessage()]);

            return [
                'status' => 'error',
                'code' => 'provider_unavailable',
                'message' => 'Payment provider is currently undergoing maintenance. Please try again shortly.',
                'temp_error' => $e->getMessage(),
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
        if ($this->isMock()) {
            return [
                'status' => 'success',
                'data' => [
                    'reference' => $reference,
                    'status' => 'success',
                    'mock' => true,
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
     * Supports Standard Webhooks / Svix (whsec_ base64 decoded key with msgId.timestamp.rawContent),
     * HMAC SHA256 and HMAC SHA512, hex and base64 encodings, and timestamped format (t=...,v1=...).
     */
    public function verifyWebhookSignature(
        string $rawContent,
        ?string $signature,
        ?string $headerTimestamp = null,
        ?string $headerMsgId = null
    ): bool {
        $skipVerify = (bool) config('services.finitypay.skip_webhook_verify', false);

        if ($skipVerify) {
            Log::info('FinityPay webhook signature verification bypassed via SKIP_WEBHOOK_VERIFY=true');

            return true;
        }

        if (! $signature || trim($signature) === '') {
            Log::warning('FinityPay webhook verification failed: No signature provided in request headers');

            return false;
        }

        $secrets = $this->getAllWebhookSecrets();
        if (empty($secrets)) {
            Log::error('FinityPay webhook verification failed: No webhook signing secrets found in configuration');

            return false;
        }

        // 1. Extract signature candidates and potential timestamps from header value
        $signatureCandidates = [];
        $extractedTimestamps = [];
        if ($headerTimestamp && trim($headerTimestamp) !== '') {
            $extractedTimestamps[] = trim($headerTimestamp);
        }

        // Split by whitespace first (Svix standard separates multiple signatures by space)
        $tokens = preg_split('/\s+/', trim($signature));
        if ($tokens === false) {
            $tokens = [trim($signature)];
        }

        foreach ($tokens as $token) {
            $token = trim($token);
            if ($token === '') {
                continue;
            }

            // Split by comma if comma-separated
            $subTokens = str_contains($token, ',') ? explode(',', $token) : [$token];

            foreach ($subTokens as $sub) {
                $sub = trim($sub);
                if ($sub === '') {
                    continue;
                }

                if (str_contains($sub, '=')) {
                    $parts = explode('=', $sub, 2);
                    $k = trim($parts[0]);
                    $v = trim($parts[1]);
                    if ($k === 't') {
                        $extractedTimestamps[] = $v;
                    } elseif ($k === 'v1' || $k === 'v2' || str_starts_with($k, 'sha')) {
                        $signatureCandidates[] = $v;
                    }
                }

                if (str_starts_with($sub, 'v1,')) {
                    $signatureCandidates[] = substr($sub, 3);
                }

                $stripped = preg_replace('/^(sha256=|sha512=|v1=|v2=|v1,|v2,)/i', '', $sub);
                if ($stripped !== null && $stripped !== '') {
                    $signatureCandidates[] = $stripped;
                }

                $signatureCandidates[] = $sub;
            }
        }

        $signatureCandidates[] = trim($signature);
        $signatureCandidates = array_values(array_unique(array_filter($signatureCandidates, fn (string $s): bool => $s !== '')));

        // 2. Extract potential message IDs and payload timestamps
        $msgIds = [];
        if ($headerMsgId && trim($headerMsgId) !== '') {
            $msgIds[] = trim($headerMsgId);
        }

        $decodedJson = json_decode($rawContent, true);
        if (is_array($decodedJson)) {
            if (! empty($decodedJson['id']) && is_string($decodedJson['id'])) {
                $msgIds[] = trim($decodedJson['id']);
            }
            if (! empty($decodedJson['data']['transaction']['id']) && is_string($decodedJson['data']['transaction']['id'])) {
                $msgIds[] = trim($decodedJson['data']['transaction']['id']);
            }
            if (! empty($decodedJson['created_at']) && is_string($decodedJson['created_at'])) {
                $parsedTime = strtotime($decodedJson['created_at']);
                if ($parsedTime !== false) {
                    $extractedTimestamps[] = (string) $parsedTime;
                }
            }
        }

        $msgIds = array_values(array_unique(array_filter($msgIds, fn (string $id): bool => $id !== '')));
        $timestamps = array_values(array_unique(array_filter($extractedTimestamps, fn (string $ts): bool => $ts !== '')));

        // 3. Construct all signed payload candidates
        $signedPayloads = [];

        // Svix Standard: "${msgId}.${timestamp}.${rawContent}"
        foreach ($msgIds as $id) {
            foreach ($timestamps as $ts) {
                $signedPayloads[] = "{$id}.{$ts}.{$rawContent}";
                $signedPayloads[] = "{$ts}.{$id}.{$rawContent}";
            }
        }

        // Timestamped formats: "${timestamp}.${rawContent}"
        foreach ($timestamps as $ts) {
            $signedPayloads[] = "{$ts}.{$rawContent}";
            $signedPayloads[] = "{$rawContent}.{$ts}";
            $signedPayloads[] = "{$ts}{$rawContent}";
        }

        // MsgId + rawContent
        foreach ($msgIds as $id) {
            $signedPayloads[] = "{$id}.{$rawContent}";
        }

        // Raw content variations (handling line endings CRLF vs LF and compact JSON)
        $signedPayloads[] = $rawContent;
        $signedPayloads[] = trim($rawContent);
        $signedPayloads[] = str_replace("\r\n", "\n", $rawContent);
        $signedPayloads[] = trim(str_replace("\r\n", "\n", $rawContent));

        if (is_array($decodedJson)) {
            $compactJson = json_encode($decodedJson);
            if ($compactJson !== false) {
                $signedPayloads[] = $compactJson;
            }
            $unescapedJson = json_encode($decodedJson, JSON_UNESCAPED_SLASHES);
            if ($unescapedJson !== false) {
                $signedPayloads[] = $unescapedJson;
            }
        }

        $signedPayloads = array_values(array_unique(array_filter($signedPayloads, fn (string $p): bool => $p !== '')));

        // 4. Test each secret and its key variants
        foreach ($secrets as $secret) {
            // Direct token match (in case provider sends secret token as verification header)
            foreach ($signatureCandidates as $candidate) {
                if (hash_equals($secret, $candidate) || hash_equals(strtolower($secret), strtolower($candidate))) {
                    Log::info('FinityPay webhook signature matched via direct secret token equality');

                    return true;
                }
            }

            $keyVariants = [];
            // If secret starts with 'whsec_', standard Svix uses base64_decode of the suffix!
            if (str_starts_with($secret, 'whsec_')) {
                $decoded = base64_decode(substr($secret, 6), true);
                if ($decoded !== false && $decoded !== '') {
                    $keyVariants[] = $decoded;
                }
            }
            $keyVariants[] = $secret;
            $b64 = base64_decode($secret, true);
            if ($b64 !== false && $b64 !== '' && $b64 !== $secret) {
                $keyVariants[] = $b64;
            }

            foreach ($keyVariants as $key) {
                foreach (['sha256', 'sha512'] as $algo) {
                    foreach ($signedPayloads as $payloadToSign) {
                        $rawHash = hash_hmac($algo, $payloadToSign, $key, true);
                        $hexHash = hash_hmac($algo, $payloadToSign, $key);
                        $b64Hash = base64_encode($rawHash);

                        foreach ($signatureCandidates as $candidate) {
                            if (
                                hash_equals($b64Hash, $candidate) ||
                                hash_equals($hexHash, $candidate) ||
                                hash_equals(strtolower($hexHash), strtolower($candidate))
                            ) {
                                Log::info('FinityPay webhook signature verified successfully', [
                                    'algorithm' => $algo,
                                    'secret_prefix' => substr($secret, 0, 8).'...',
                                    'has_msg_id' => ! empty($msgIds),
                                    'has_timestamp' => ! empty($timestamps),
                                ]);

                                return true;
                            }
                        }
                    }
                }
            }
        }

        Log::warning('FinityPay webhook signature mismatch after checking all candidate secrets, formats, and algorithms', [
            'signature_received' => $signature,
            'header_timestamp' => $headerTimestamp,
            'header_msg_id' => $headerMsgId,
            'raw_content_length' => strlen($rawContent),
            'secrets_tried' => count($secrets),
            'secret_prefixes' => array_map(fn (string $s): string => substr($s, 0, 8).'...', $secrets),
        ]);

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
        // Handle Customer Events (customer.created, customer.updated)
        // =====================================================================
        if ($eventType === 'customer.created' || $eventType === 'customer.updated') {
            /** @var array<string, mixed> $data */
            $data = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : [];
            $providerCustomerId = (string) ($data['id'] ?? $data['uuid'] ?? '');
            $email = (string) ($data['email'] ?? '');
            if ($providerCustomerId !== '' && $email !== '') {
                Customer::where('email', $email)
                    ->whereNull('provider_customer_id')
                    ->update(['provider_customer_id' => $providerCustomerId]);
            }

            $event->update([
                'status' => 'processed',
                'processed_at' => now(),
            ]);

            return true;
        }

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
        $accountId = (string) ($account['id'] ?? '');

        if (! $fundingRecord && ($accountNumber !== '' || $accountRef !== '' || $accountId !== '')) {
            /** @var CustomerVirtualAccount|null $virtualAccount */
            $virtualAccount = CustomerVirtualAccount::where(function ($q) use ($accountNumber, $accountRef, $accountId): void {
                if ($accountNumber !== '') {
                    $q->orWhere('account_number', $accountNumber);
                }
                if ($accountRef !== '') {
                    $q->orWhere('account_reference', $accountRef);
                }
                if ($accountId !== '') {
                    $q->orWhere('provider_account_id', $accountId);
                }
            })->first();

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

        // If not matched by account, match by customer provider UUID or email
        if (! $fundingRecord) {
            $customerUuid = (string) ($customerData['uuid'] ?? $customerData['id'] ?? '');
            /** @var Customer|null $customer */
            $customer = null;

            if ($customerUuid !== '') {
                $customer = Customer::where('provider_customer_id', $customerUuid)->first();
            }

            if (! $customer && $customerEmail !== '') {
                $customer = Customer::where('email', $customerEmail)->first();
            }

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
