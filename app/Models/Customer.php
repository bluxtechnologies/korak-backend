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
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, CustomerTrustedDevice> $trustedDevices
 * @property-read Collection<int, CustomerDeviceChallenge> $deviceChallenges
 * @property-read Wallet|null $wallet
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
        'email',
        'email_verified_at',
        'password',
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
}
