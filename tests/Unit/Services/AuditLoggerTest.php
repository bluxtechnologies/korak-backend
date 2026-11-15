<?php

use App\Enums\AuditEventName;
use App\Models\Customer;
use App\Services\Audit\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('records an audit event with actor, subject, and request context per Doc 12', function (): void {
    $customer = Customer::factory()->create();

    $request = Request::create('/api/v1/test', 'POST', [], [], [], [
        'REMOTE_ADDR' => '10.20.30.40',
        'HTTP_USER_AGENT' => 'TestBrowser/1.0',
        'HTTP_X_REQUEST_ID' => 'req-ulid-12345',
    ]);

    $logger = new AuditLogger($request);

    $event = $logger->log(
        event: AuditEventName::AUTH_LOGIN_SUCCESS,
        actor: $customer,
        subject: $customer,
        metadata: ['auth_method' => 'password']
    );

    expect($event->exists)->toBeTrue()
        ->and($event->event)->toBe(AuditEventName::AUTH_LOGIN_SUCCESS)
        ->and($event->actor_type)->toBe($customer->getMorphClass())
        ->and($event->actor_id)->toBe($customer->id)
        ->and($event->subject_type)->toBe($customer->getMorphClass())
        ->and($event->subject_id)->toBe($customer->id)
        ->and($event->ip_address)->toBe('10.20.30.40')
        ->and($event->user_agent)->toBe('TestBrowser/1.0')
        ->and($event->request_id)->toBe('req-ulid-12345')
        ->and($event->metadata)->toBe(['auth_method' => 'password']);
});

it('scrubs and redacts sensitive keys from metadata recursively per Doc 12 §3 & Doc 08 §12', function (): void {
    $logger = new AuditLogger;

    $dirtyMetadata = [
        'username' => 'alice',
        'password' => 'super-secret-password',
        'password_confirmation' => 'super-secret-password',
        'otp' => '123456',
        'token' => 'plain-text-token-here',
        'device_token' => 'random_device_token',
        'nested' => [
            'safe_field' => 'visible',
            'secret_key' => 'shhh',
            'api_token' => 'my-token',
        ],
    ];

    $clean = $logger->redactSensitive($dirtyMetadata);

    expect($clean['username'])->toBe('alice')
        ->and($clean['password'])->toBe('[REDACTED]')
        ->and($clean['password_confirmation'])->toBe('[REDACTED]')
        ->and($clean['otp'])->toBe('[REDACTED]')
        ->and($clean['token'])->toBe('[REDACTED]')
        ->and($clean['device_token'])->toBe('[REDACTED]')
        ->and($clean['nested']['safe_field'])->toBe('visible')
        ->and($clean['nested']['secret_key'])->toBe('[REDACTED]')
        ->and($clean['nested']['api_token'])->toBe('[REDACTED]');
});

it('enforces append-only immutability by preventing update on audit events per Doc 12 §4', function (): void {
    $logger = new AuditLogger;
    $event = $logger->log(AuditEventName::AUTH_LOGIN_SUCCESS);

    expect(fn () => $event->update(['ip_address' => '1.1.1.1']))
        ->toThrow(LogicException::class, 'Audit events are append-only and cannot be updated.');
});

it('enforces append-only immutability by preventing delete on audit events per Doc 12 §4', function (): void {
    $logger = new AuditLogger;
    $event = $logger->log(AuditEventName::AUTH_LOGIN_SUCCESS);

    expect(fn () => $event->delete())
        ->toThrow(LogicException::class, 'Audit events are append-only and cannot be deleted.');
});
