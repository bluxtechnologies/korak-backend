<?php

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
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
        $signature = $this->extractSignature($request);
        $timestamp = $this->extractTimestamp($request);
        $msgId = $this->extractMsgId($request);
        $rawContent = $request->getContent();

        Log::info('Incoming FinityPay webhook received', [
            'method' => $request->method(),
            'path' => $request->path(),
            'has_signature' => ! empty($signature),
            'signature_preview' => $signature ? substr($signature, 0, 16).'...' : null,
            'header_timestamp' => $timestamp,
            'msg_id' => $msgId,
            'header_keys' => array_keys($request->headers->all()),
            'payload_type' => $request->input('type') ?? $request->input('event'),
            'ip' => $request->ip(),
        ]);

        if (! $this->finityPayService->verifyWebhookSignature($rawContent, $signature, $timestamp, $msgId)) {
            Log::warning('FinityPay webhook signature rejected', [
                'extracted_signature' => $signature,
                'header_keys' => array_keys($request->headers->all()),
                'ip' => $request->ip(),
            ]);

            return ApiResponse::error(
                message: 'Invalid webhook signature.',
                code: 'invalid_webhook_signature',
                status: 401
            );
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
     * Extract signature from all known HTTP headers, server variables, and query parameters.
     */
    protected function extractSignature(Request $request): ?string
    {
        $knownHeaders = [
            'webhook-signature',
            'svix-signature',
            'x-webhook-signature',
            'x-svix-signature',
            'x-cofinance-signature',
            'x-finity-signature',
            'x-finitypay-signature',
            'x-signature-sha256',
            'x-hub-signature-256',
            'x-hub-signature',
            'x-signature',
            'x-fin-signature',
            'signature',
            'x-pay-signature',
            'x-secret-hash',
        ];

        foreach ($knownHeaders as $header) {
            $val = $request->header($header);
            if (is_string($val) && trim($val) !== '') {
                return trim($val);
            }
        }

        // Check LiteSpeed / Apache raw server headers
        $serverKeys = [
            'HTTP_WEBHOOK_SIGNATURE',
            'HTTP_SVIX_SIGNATURE',
            'HTTP_X_WEBHOOK_SIGNATURE',
            'HTTP_X_SVIX_SIGNATURE',
            'HTTP_X_COFINANCE_SIGNATURE',
            'HTTP_X_FINITY_SIGNATURE',
            'HTTP_X_FINITYPAY_SIGNATURE',
            'HTTP_X_SIGNATURE',
            'HTTP_SIGNATURE',
        ];

        foreach ($serverKeys as $serverKey) {
            $val = $request->server($serverKey);
            if (is_string($val) && trim($val) !== '') {
                return trim($val);
            }
        }

        // Fuzzy search across all headers for 'signature' or 'hash'
        foreach ($request->headers->all() as $name => $values) {
            $lower = strtolower($name);
            if (str_contains($lower, 'signature') || str_contains($lower, 'hash')) {
                $val = $values[0] ?? null;
                if (is_string($val) && trim($val) !== '') {
                    return trim($val);
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
     */
    protected function extractTimestamp(Request $request): ?string
    {
        $timestampHeaders = [
            'webhook-timestamp',
            'svix-timestamp',
            'x-webhook-timestamp',
            'x-svix-timestamp',
            'x-cofinance-timestamp',
            'x-finity-timestamp',
            'x-finitypay-timestamp',
            'x-timestamp',
            'timestamp',
        ];

        foreach ($timestampHeaders as $header) {
            $val = $request->header($header);
            if (is_string($val) && trim($val) !== '') {
                return trim($val);
            }
        }

        $serverKeys = [
            'HTTP_WEBHOOK_TIMESTAMP',
            'HTTP_SVIX_TIMESTAMP',
            'HTTP_X_WEBHOOK_TIMESTAMP',
            'HTTP_X_SVIX_TIMESTAMP',
            'HTTP_X_COFINANCE_TIMESTAMP',
            'HTTP_X_FINITY_TIMESTAMP',
            'HTTP_X_FINITYPAY_TIMESTAMP',
            'HTTP_X_TIMESTAMP',
        ];

        foreach ($serverKeys as $serverKey) {
            $val = $request->server($serverKey);
            if (is_string($val) && trim($val) !== '') {
                return trim($val);
            }
        }

        return null;
    }

    /**
     * Extract message / event ID from headers, server variables, or payload.
     */
    protected function extractMsgId(Request $request): ?string
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
            $val = $request->header($header);
            if (is_string($val) && trim($val) !== '') {
                return trim($val);
            }
        }

        $serverKeys = [
            'HTTP_WEBHOOK_ID',
            'HTTP_SVIX_ID',
            'HTTP_X_WEBHOOK_ID',
            'HTTP_X_SVIX_ID',
            'HTTP_X_REQUEST_ID',
        ];

        foreach ($serverKeys as $serverKey) {
            $val = $request->server($serverKey);
            if (is_string($val) && trim($val) !== '') {
                return trim($val);
            }
        }

        $payloadId = $request->input('id');
        if (is_string($payloadId) && trim($payloadId) !== '') {
            return trim($payloadId);
        }

        return null;
    }
}
