<?php

namespace App\Services\Audit;

use App\Enums\AuditEventName;
use App\Models\AuditEvent;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    /**
     * Keys that must never be recorded in audit metadata (Doc 12 §3, Doc 08 §12).
     *
     * @var list<string>
     */
    protected const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'token',
        'otp',
        'code',
        'device_token',
        'device_token_hash',
        'secret',
        'authorization',
        'cookie',
        'cvv',
        'pin',
    ];

    /**
     * Create a new audit logger instance.
     */
    public function __construct(
        protected ?Request $request = null,
    ) {
        $this->request = $request ?: (app()->bound('request') ? request() : null);
    }

    /**
     * Record an audit event into the append-only audit trail.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function log(
        AuditEventName $event,
        ?Model $actor = null,
        ?Model $subject = null,
        array $metadata = [],
    ): AuditEvent {
        // Resolve actor if not provided
        if ($actor === null) {
            $actor = Auth::guard('customer')->user()
                ?: Auth::guard('admin')->user();
        }

        $requestId = $this->request?->header('X-Request-ID');
        $ip = $this->request?->ip();
        $userAgent = $this->request?->userAgent();

        if ($userAgent && strlen($userAgent) > 500) {
            $userAgent = substr($userAgent, 0, 500);
        }

        $cleanMetadata = $this->redactSensitive($metadata);

        return AuditEvent::create([
            'occurred_at' => Carbon::now(),
            'actor_type' => $actor ? $actor->getMorphClass() : null,
            'actor_id' => $actor?->getKey(),
            'event' => $event,
            'subject_type' => $subject ? $subject->getMorphClass() : null,
            'subject_id' => $subject?->getKey(),
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'request_id' => is_string($requestId) && $requestId !== '' ? $requestId : null,
            'metadata' => empty($cleanMetadata) ? null : $cleanMetadata,
            'created_at' => Carbon::now(),
        ]);
    }

    /**
     * Redact any sensitive keys from metadata recursively.
     *
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    public function redactSensitive(array $metadata): array
    {
        $clean = [];

        foreach ($metadata as $key => $value) {
            $lowerKey = strtolower((string) $key);

            if ($this->isSensitiveKey($lowerKey)) {
                $clean[$key] = '[REDACTED]';

                continue;
            }

            if (is_array($value)) {
                /** @var array<string, mixed> $value */
                $clean[$key] = $this->redactSensitive($value);
            } else {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }

    /**
     * Check if a key name matches any known sensitive parameter.
     */
    protected function isSensitiveKey(string $key): bool
    {
        foreach (self::SENSITIVE_KEYS as $sensitive) {
            if ($key === $sensitive || str_contains($key, $sensitive)) {
                return true;
            }
        }

        return false;
    }
}
