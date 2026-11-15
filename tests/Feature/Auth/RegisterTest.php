<?php

use App\Models\Customer;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('registers a new customer and returns 201 with customer resource per Doc 06', function (): void {
    Event::fake([Registered::class]);

    $payload = [
        'username' => 'johndoe',
        'email' => 'john@example.com',
        'password' => 'SecurePass123!',
        'password_confirmation' => 'SecurePass123!',
    ];

    $response = $this->postJson('/api/v1/auth/register', $payload);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => [
                'id',
                'username',
                'email',
                'email_verified_at',
                'created_at',
            ],
            'meta',
        ])
        ->assertJson([
            'data' => [
                'username' => 'johndoe',
                'email' => 'john@example.com',
                'email_verified_at' => null,
            ],
        ]);

    $customer = Customer::where('email', 'john@example.com')->first();
    expect($customer)->not->toBeNull()
        ->and($customer->username)->toBe('johndoe')
        ->and(Hash::check('SecurePass123!', $customer->password))->toBeTrue();

    // Verify public ID is a valid ULID and NOT an internal integer
    $data = $response->json('data');
    expect(Str::isUlid($data['id']))->toBeTrue()
        ->and($data['id'])->toBe($customer->ulid)
        ->and($data)->not->toHaveKey('password')
        ->and($data)->not->toHaveKey('remember_token');

    // Verify Registered event was dispatched
    Event::assertDispatched(Registered::class, function (Registered $event) use ($customer): bool {
        return $event->user instanceof Customer && $event->user->id === $customer->id;
    });

    // Verify correlation header
    expect($response->headers->has('X-Request-ID'))->toBeTrue();
});

it('fails validation when required fields are missing', function (): void {
    $response = $this->postJson('/api/v1/auth/register', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['username', 'email', 'password'])
        ->assertJson([
            'code' => 'validation_failed',
        ]);
});

it('fails validation when username or email is already taken', function (): void {
    Customer::factory()->create([
        'username' => 'existing_user',
        'email' => 'existing@example.com',
    ]);

    $response = $this->postJson('/api/v1/auth/register', [
        'username' => 'existing_user',
        'email' => 'existing@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['username', 'email'])
        ->assertJson([
            'code' => 'validation_failed',
        ]);
});

it('fails validation when password confirmation does not match', function (): void {
    $response = $this->postJson('/api/v1/auth/register', [
        'username' => 'valid_user',
        'email' => 'valid@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'DifferentPassword!',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password'])
        ->assertJson([
            'code' => 'validation_failed',
        ]);
});

it('fails validation when password is too short', function (): void {
    $response = $this->postJson('/api/v1/auth/register', [
        'username' => 'valid_user',
        'email' => 'valid@example.com',
        'password' => 'short',
        'password_confirmation' => 'short',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password'])
        ->assertJson([
            'code' => 'validation_failed',
        ]);
});

it('fails validation when username contains invalid characters', function (): void {
    $response = $this->postJson('/api/v1/auth/register', [
        'username' => 'invalid username with spaces',
        'email' => 'valid@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['username'])
        ->assertJson([
            'code' => 'validation_failed',
            'errors' => [
                'username' => [
                    'The username may only contain letters, numbers, dashes, underscores, and periods.',
                ],
            ],
        ]);
});
