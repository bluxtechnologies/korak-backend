<?php

namespace App\Exceptions;

use App\Http\Responses\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

final class ApiExceptionHandler
{
    /**
     * Render an exception into an HTTP response conforming to Doc 06 envelope standards.
     */
    public static function render(Throwable $e, Request $request): JsonResponse
    {
        if ($e instanceof ValidationException) {
            /** @var array<string, list<string>> $errors */
            $errors = $e->errors();

            return ApiResponse::error(
                message: $e->getMessage(),
                code: 'validation_failed',
                status: 422,
                errors: $errors
            );
        }

        if ($e instanceof InsufficientBalanceException) {
            return ApiResponse::error(
                message: $e->getMessage() !== '' ? $e->getMessage() : 'Insufficient wallet balance.',
                code: 'insufficient_balance',
                status: 422
            );
        }

        if ($e instanceof AuthenticationException) {
            return ApiResponse::error(
                message: 'Unauthenticated.',
                code: 'unauthenticated',
                status: 401
            );
        }

        if ($e instanceof AuthorizationException || $e instanceof AccessDeniedHttpException) {
            return ApiResponse::error(
                message: 'This action is unauthorized.',
                code: 'forbidden',
                status: 403
            );
        }

        if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
            return ApiResponse::error(
                message: 'Resource not found.',
                code: 'not_found',
                status: 404
            );
        }

        if ($e instanceof MethodNotAllowedHttpException) {
            $response = ApiResponse::error(
                message: 'Method not allowed.',
                code: 'method_not_allowed',
                status: 405
            );

            /** @var array<string, string|list<string>> $headers */
            $headers = $e->getHeaders();
            if ($headers !== []) {
                $response->headers->add($headers);
            }

            return $response;
        }

        if ($e instanceof ThrottleRequestsException || $e instanceof TooManyRequestsHttpException) {
            $response = ApiResponse::error(
                message: 'Too many requests. Please try again later.',
                code: 'too_many_requests',
                status: 429
            );

            /** @var array<string, string|list<string>> $headers */
            $headers = $e->getHeaders();
            if ($headers !== []) {
                $response->headers->add($headers);
            }

            return $response;
        }

        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();
            $code = match ($status) {
                400 => 'bad_request',
                409 => 'conflict',
                419 => 'csrf_token_mismatch',
                422 => 'validation_failed',
                423 => 'locked',
                default => 'http_error',
            };

            $message = $e->getMessage() !== ''
                ? $e->getMessage()
                : (Response::$statusTexts[$status] ?? 'An error occurred.');

            $response = ApiResponse::error(
                message: $message,
                code: $code,
                status: $status
            );

            /** @var array<string, string|list<string>> $headers */
            $headers = $e->getHeaders();
            if ($headers !== []) {
                $response->headers->add($headers);
            }

            return $response;
        }

        $referenceId = $request->header('X-Request-ID');
        if (! is_string($referenceId) || $referenceId === '') {
            $referenceId = (string) Str::ulid();
        }

        Log::error('Server Error: '.$e->getMessage(), [
            'reference_id' => $referenceId,
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'exception' => $e,
        ]);

        return ApiResponse::error(
            message: 'An unexpected server error occurred.',
            code: 'server_error',
            status: 500,
            referenceId: $referenceId
        );
    }
}
