<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureRequestId
{
    /**
     * Header key used for request correlation.
     */
    public const HEADER_NAME = 'X-Request-ID';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $incomingId = $request->header(self::HEADER_NAME);

        $requestId = $this->isValidRequestId($incomingId)
            ? (string) $incomingId
            : (string) Str::ulid();

        $request->headers->set(self::HEADER_NAME, $requestId);
        $request->attributes->set('request_id', $requestId);

        Context::add('request_id', $requestId);

        $response = $next($request);

        $response->headers->set(self::HEADER_NAME, $requestId);

        return $response;
    }

    /**
     * Determine if the given request ID conforms to safe correlation ID standards.
     */
    protected function isValidRequestId(mixed $id): bool
    {
        if (! is_string($id)) {
            return false;
        }

        return (bool) preg_match('/^[a-zA-Z0-9_\-\.]{1,100}$/', $id);
    }
}
