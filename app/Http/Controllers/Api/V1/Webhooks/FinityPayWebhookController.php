<?php

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Providers\FinityPayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        $signature = $request->header('x-cofinance-signature')
            ?: $request->header('x-finity-signature')
            ?: $request->header('x-finitypay-signature')
            ?: $request->header('x-signature')
            ?: $request->header('signature');

        $timestamp = $request->header('x-cofinance-timestamp')
            ?: $request->header('x-timestamp');

        $rawContent = $request->getContent();

        if (! $this->finityPayService->verifyWebhookSignature($rawContent, $signature, $timestamp)) {
            return ApiResponse::error(
                message: 'Invalid webhook signature.',
                code: 'invalid_webhook_signature',
                status: 401
            );
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->all();
        $eventReference = (string) Str::uuid();

        $this->finityPayService->processWebhook($payload, $eventReference);

        return response()->json([
            'status' => 'success',
            'message' => 'Webhook received.',
        ]);
    }
}
