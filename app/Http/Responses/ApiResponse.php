<?php

namespace App\Http\Responses;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class ApiResponse
{
    /**
     * Build a standardized success JSON response.
     *
     * Format: { "data": ..., "meta": ... }
     *
     * @param  array<string, mixed>  $meta
     */
    public static function success(mixed $data = null, array $meta = [], int $status = 200): JsonResponse
    {
        $payload = [
            'data' => $data ?? (object) [],
            'meta' => empty($meta) ? (object) [] : $meta,
        ];

        return new JsonResponse($payload, $status);
    }

    /**
     * Build a standardized paginated JSON response.
     *
     * Format: { "data": [...], "meta": { "current_page": 1, "per_page": 15, "total": 120, "last_page": 8, ... } }
     *
     * @template TKey of array-key
     * @template TValue
     *
     * @param  LengthAwarePaginator<TKey, TValue>  $paginator
     * @param  array<string, mixed>  $meta
     */
    public static function paginated(LengthAwarePaginator $paginator, array $meta = [], int $status = 200): JsonResponse
    {
        $paginationMeta = [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];

        $payload = [
            'data' => array_values($paginator->items()),
            'meta' => array_merge($paginationMeta, $meta),
        ];

        return new JsonResponse($payload, $status);
    }

    /**
     * Build a standardized error JSON response.
     *
     * Format: { "message": "...", "code": "..." }
     * With validation errors: { "message": "...", "code": "...", "errors": { ... } }
     * With reference ID (500): { "message": "...", "code": "...", "reference_id": "..." }
     *
     * @param  array<string, list<string>>|null  $errors
     */
    public static function error(
        string $message,
        string $code,
        int $status = 400,
        ?array $errors = null,
        ?string $referenceId = null,
    ): JsonResponse {
        $payload = [
            'message' => $message,
            'code' => $code,
        ];

        if ($errors !== null && $errors !== []) {
            $payload['errors'] = $errors;
        }

        if ($referenceId !== null && $referenceId !== '') {
            $payload['reference_id'] = $referenceId;
        }

        return new JsonResponse($payload, $status);
    }

    /**
     * Build a 204 No Content response.
     */
    public static function noContent(): Response
    {
        return new Response('', 204);
    }
}
