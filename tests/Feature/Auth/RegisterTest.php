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
        'phone_number' => '+2348012345678',
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
                'phone_number',
                'email_verified_at',
                'created_at',
            ],
            'meta',
        ])
        ->assertJson([
            'data' => [
                'username' => 'johndoe',
                'email' => 'john@example.com',
                'phone_number' => '+2348012345678',
                'email_verified_at' => null,
            ],
        ]);

    $customer = Customer::where('email', 'john@example.com')->first();
    expect($customer)->not->toBeNull()
        ->and($customer->username)->toBe('johndoe')
        ->and($customer->phone_number)->toBe('+2348012345678')
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

it('registers a new customer using phone_number and name without username, auto-generating username', function (): void {
    Event::fake([Registered::class]);

    $payload = [
        'name' => 'Collins Okeke',
        'email' => 'collins@example.com',
        'phone_number' => '+2348012345678',
        'password' => 'Secret123!',
        'password_confirmation' => 'Secret123!',
        'terms' => true,
    ];

    $response = $this->postJson('/api/v1/auth/register', $payload);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'first_name',
                'last_name',
                'email',
                'phone_number',
                'email_verified_at',
                'created_at',
            ],
            'meta',
        ])
        ->assertJson([
            'data' => [
                'name' => 'Collins Okeke',
                'first_name' => 'Collins',
                'last_name' => 'Okeke',
                'email' => 'collins@example.com',
                'phone_number' => '+2348012345678',
                'email_verified_at' => null,
            ],
        ]);

    $customer = Customer::where('email', 'collins@example.com')->first();
    expect($customer)->not->toBeNull()
        ->and($customer->first_name)->toBe('Collins')
        ->and($customer->last_name)->toBe('Okeke')
        ->and($customer->phone_number)->toBe('+2348012345678')
        ->and($customer->username)->not->toBeEmpty()
        ->and(Hash::check('Secret123!', $customer->password))->toBeTrue();
});

it('fails validation when required fields are missing', function (): void {
    $response = $this->postJson('/api/v1/auth/register', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['phone_number', 'email', 'password'])
        ->assertJsonMissingValidationErrors(['username'])
        ->assertJson([
            'code' => 'validation_failed',
        ]);
});

it('fails validation when phone_number or email is already taken', function (): void {
    Customer::factory()->create([
        'username' => 'existing_user',
        'email' => 'existing@example.com',
        'phone_number' => '+2348012345678',
    ]);

    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'New User',
        'email' => 'existing@example.com',
        'phone_number' => '+2348012345678',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['phone_number', 'email'])
        ->assertJson([
            'code' => 'validation_failed',
            'errors' => [
                'phone_number' => [
                    'The phone number has already been taken.',
                ],
            ],
        ]);
});

it('fails validation when phone_number has invalid format or is too short', function (): void {
    // Too short (min 7)
    $responseShort = $this->postJson('/api/v1/auth/register', [
        'name' => 'Test User',
        'email' => 'shortphone@example.com',
        'phone_number' => '12345',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $responseShort->assertStatus(422)
        ->assertJsonValidationErrors(['phone_number']);

    // Invalid characters
    $responseInvalid = $this->postJson('/api/v1/auth/register', [
        'name' => 'Test User',
        'email' => 'invalidphone@example.com',
        'phone_number' => 'phone-number-abc',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $responseInvalid->assertStatus(422)
        ->assertJsonValidationErrors(['phone_number'])
        ->assertJson([
            'code' => 'validation_failed',
            'errors' => [
                'phone_number' => [
                    'The phone number format is invalid.',
                ],
            ],
        ]);
});

it('fails validation when password confirmation does not match', function (): void {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Valid User',
        'email' => 'valid@example.com',
        'phone_number' => '+2348012345678',
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
        'name' => 'Valid User',
        'email' => 'valid@example.com',
        'phone_number' => '+2348012345678',
        'password' => 'short',
        'password_confirmation' => 'short',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password'])
        ->assertJson([
            'code' => 'validation_failed',
        ]);
});

it('fails validation when username is explicitly provided with invalid characters', function (): void {
    $response = $this->postJson('/api/v1/auth/register', [
        'username' => 'invalid username with spaces',
        'email' => 'valid@example.com',
        'phone_number' => '+2348012345678',
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
