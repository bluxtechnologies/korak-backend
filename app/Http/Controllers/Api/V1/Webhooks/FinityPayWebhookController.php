<?php

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Providers\FinityPayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FinityPayWebhookController extends Controller
{
    /**
     * Create a new webhook controller instance.
     */
    public function __construct(
        protected FinityPayService $finityPayService,
    ) {}

    /**
     * Handle incoming webhook requests from FinityPay / CoFinance.
     *
     * Per Doc 14 §4 and Roadmap B14.3:
     * - Webhook signature verification.
     * - Replay protection & idempotent crediting.
     * - Fast acknowledgment.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $allHeaders = $this->collectAllHeaders($request);
        $signature = $this->extractSignature($request, $allHeaders);
        $timestamp = $this->extractTimestamp($request, $allHeaders);
        $msgId = $this->extractMsgId($request, $allHeaders);
        $rawContent = $request->getContent() ?: (string) file_get_contents('php://input');

        Log::info('Incoming FinityPay webhook received', [
            'method' => $request->method(),
            'path' => $request->path(),
            'has_signature' => ! empty($signature),
            'signature_preview' => $signature ? substr($signature, 0, 16).'...' : null,
            'header_timestamp' => $timestamp,
            'msg_id' => $msgId,
            'header_keys' => array_keys($allHeaders),
            'payload_type' => $request->input('type') ?? $request->input('event'),
            'ip' => $request->ip(),
        ]);

        if (! $this->finityPayService->verifyWebhookSignature($rawContent, $signature, $timestamp, $msgId)) {
            Log::warning('FinityPay webhook signature rejected', [
                'extracted_signature' => $signature,
                'header_keys' => array_keys($allHeaders),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'status' => 'error',
                'code' => 'invalid_webhook_signature',
                'message' => 'Invalid webhook signature.',
                'diagnostic' => [
                    'version' => '2026.11.25-svix-sha512',
                    'signature_found' => ! empty($signature),
                    'signature_length' => $signature ? strlen($signature) : 0,
                    'signature_preview' => $signature ? substr($signature, 0, 16).'...' : null,
                    'header_keys_detected' => array_keys($allHeaders),
                    'secrets_configured' => count($this->finityPayService->getAllWebhookSecrets()),
                ],
            ], 401);
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->all();
        $eventReference = (string) Str::uuid();

        $processed = $this->finityPayService->processWebhook($payload, $eventReference);

        Log::info('FinityPay webhook processed', [
            'event_reference' => $eventReference,
            'payload_type' => $payload['type'] ?? $payload['event'] ?? 'unknown',
            'processed' => $processed,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Webhook received.',
        ]);
    }

    /**
     * Gather all incoming request headers from getallheaders(), apache_request_headers(),
     * Symfony request headers, and $_SERVER.
     *
     * @return array<string, string>
     */
    protected function collectAllHeaders(Request $request): array
    {
        $headers = [];

        if (function_exists('getallheaders')) {
            $native = (array) getallheaders();
            foreach ($native as $k => $v) {
                if (is_string($k) && (is_string($v) || is_numeric($v))) {
                    $headers[strtolower(trim($k))] = trim((string) $v);
                }
            }
        }

        if (function_exists('apache_request_headers')) {
            $native = (array) apache_request_headers();
            foreach ($native as $k => $v) {
                if (is_string($k) && (is_string($v) || is_numeric($v))) {
                    $headers[strtolower(trim($k))] = trim((string) $v);
                }
            }
        }

        foreach ($request->headers->all() as $k => $values) {
            $val = $values[0] ?? null;
            if (is_string($val) && trim($val) !== '') {
                $headers[strtolower(trim($k))] = trim($val);
            }
        }

        foreach ($_SERVER as $k => $v) {
            if (is_string($v) && trim($v) !== '') {
                $lower = strtolower($k);
                if (str_starts_with($lower, 'http_')) {
                    $norm = str_replace('_', '-', substr($lower, 5));
                    $headers[$norm] = trim($v);
                }
                $headers[$lower] = trim($v);
            }
        }

        return $headers;
    }

    /**
     * Extract signature from all known HTTP headers, server variables, and query parameters.
     *
     * @param  array<string, string>  $allHeaders
     */
    protected function extractSignature(Request $request, array $allHeaders): ?string
    {
        $knownHeaders = [
            'x-cofinance-signature',
            'x-finity-signature',
            'x-finitypay-signature',
            'webhook-signature',
            'svix-signature',
            'x-webhook-signature',
            'x-svix-signature',
            'x-signature-sha256',
            'x-signature-sha512',
            'x-hub-signature-256',
            'x-hub-signature',
            'x-signature',
            'x-fin-signature',
            'signature',
            'x-pay-signature',
            'x-secret-hash',
        ];

        foreach ($knownHeaders as $header) {
            if (! empty($allHeaders[$header])) {
                return $allHeaders[$header];
            }
        }

        // Fuzzy search across all headers for 'signature' or 'hash'
        foreach ($allHeaders as $name => $val) {
            if (str_contains($name, 'signature') || str_contains($name, 'hash')) {
                if ($val !== '') {
                    return $val;
                }
            }
        }

        // Query or JSON payload attribute
        $querySig = $request->query('signature') ?? $request->query('sig');
        if (is_string($querySig) && trim($querySig) !== '') {
            return trim($querySig);
        }

        $inputSig = $request->input('signature') ?? $request->input('sig');
        if (is_string($inputSig) && trim($inputSig) !== '') {
            return trim($inputSig);
        }

        return null;
    }

    /**
     * Extract timestamp from headers or server variables.
     *
     * @param  array<string, string>  $allHeaders
     */
    protected function extractTimestamp(Request $request, array $allHeaders): ?string
    {
        $timestampHeaders = [
            'x-cofinance-timestamp',
            'webhook-timestamp',
            'svix-timestamp',
            'x-webhook-timestamp',
            'x-svix-timestamp',
            'x-finity-timestamp',
            'x-finitypay-timestamp',
            'x-timestamp',
            'timestamp',
        ];

        foreach ($timestampHeaders as $header) {
            if (! empty($allHeaders[$header])) {
                return $allHeaders[$header];
            }
        }

        return null;
    }

    /**
     * Extract message / event ID from headers, server variables, or payload.
     *
     * @param  array<string, string>  $allHeaders
     */
    protected function extractMsgId(Request $request, array $allHeaders): ?string
    {
        $idHeaders = [
            'webhook-id',
            'svix-id',
            'x-webhook-id',
            'x-svix-id',
            'x-cofinance-id',
            'x-finity-id',
            'x-request-id',
        ];

        foreach ($idHeaders as $header) {
            if (! empty($allHeaders[$header])) {
                return $allHeaders[$header];
            }
        }

        $payloadId = $request->input('id');
        if (is_string($payloadId) && trim($payloadId) !== '') {
            return trim($payloadId);
        }

        return null;
    }
}
