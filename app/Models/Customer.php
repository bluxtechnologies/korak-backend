<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use App\Notifications\ResetPasswordNotification;
use Carbon\Carbon;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property int $id
 * @property string $ulid
 * @property string $username
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string $email
 * @property string|null $phone
 * @property string|null $phone_number
 * @property string|null $provider_customer_id
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property bool $device_verification_enabled
 * @property bool $is_locked
 * @property Carbon|null $locked_at
 * @property string|null $lock_reason
 * @property string|null $remember_token
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, CustomerTrustedDevice> $trustedDevices
 * @property-read Collection<int, CustomerDeviceChallenge> $deviceChallenges
 * @property-read Wallet|null $wallet
 * @property-read CustomerVirtualAccount|null $virtualAccount
 */
class Customer extends Authenticatable
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    use HasPublicUlid;
    use Notifiable;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'customers';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'ulid',
        'username',
        'name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'phone_number',
        'provider_customer_id',
        'email_verified_at',
        'password',
        'device_verification_enabled',
        'is_locked',
        'locked_at',
        'lock_reason',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'device_verification_enabled' => 'boolean',
            'is_locked' => 'boolean',
            'locked_at' => 'datetime',
        ];
    }

    /**
     * Send the password reset notification.
     *
     * @param  string  $token
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * Get the customer's trusted devices.
     *
     * @return HasMany<CustomerTrustedDevice, $this>
     */
    public function trustedDevices(): HasMany
    {
        return $this->hasMany(CustomerTrustedDevice::class);
    }

    /**
     * Get the customer's active device verification challenges.
     *
     * @return HasMany<CustomerDeviceChallenge, $this>
     */
    public function deviceChallenges(): HasMany
    {
        return $this->hasMany(CustomerDeviceChallenge::class);
    }

    /**
     * Get the customer's wallet.
     *
     * @return HasOne<Wallet, $this>
     */
    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    /**
     * Set the customer's phone number and synchronize with phone attribute.
     */
    public function setPhoneNumberAttribute(?string $value): void
    {
        $this->attributes['phone_number'] = $value;
        if (empty($this->attributes['phone'])) {
            $this->attributes['phone'] = $value;
        }
    }

    /**
     * Set the customer's composite name and synchronize first_name and last_name.
     */
    public function setNameAttribute(?string $value): void
    {
        if ($value !== null && trim($value) !== '') {
            $parts = explode(' ', trim($value), 2);
            $this->attributes['first_name'] = $parts[0];
            $this->attributes['last_name'] = $parts[1] ?? null;
        }
    }

    /**
     * Get the customer's full composite name or fallback to username.
     */
    public function getNameAttribute(): string
    {
        $fullName = trim("{$this->first_name} {$this->last_name}");

        return $fullName !== '' ? $fullName : $this->username;
    }

    /**
     * Set the customer's phone and synchronize with phone_number attribute.
     */
    public function setPhoneAttribute(?string $value): void
    {
        $this->attributes['phone'] = $value;
        if (empty($this->attributes['phone_number'])) {
            $this->attributes['phone_number'] = $value;
        }
    }

    /**
     * Get the customer's dedicated virtual account.
     *
     * @return HasOne<CustomerVirtualAccount, $this>
     */
    public function virtualAccount(): HasOne
    {
        return $this->hasOne(CustomerVirtualAccount::class);
    }

    /**
     * Get the customer's support tickets.
     *
     * @return HasMany<SupportTicket, $this>
     */
    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }
}
