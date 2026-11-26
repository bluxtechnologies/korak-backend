<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $ulid
 * @property int $customer_id
 * @property string $device_token_hash
 * @property string|null $device_name
 * @property string|null $ip_address
 * @property Carbon|null $last_used_at
 * @property Carbon|null $last_seen_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Customer $customer
 */
class CustomerTrustedDevice extends Model
{
    use HasFactory;
    use HasPublicUlid;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'customer_trusted_devices';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'ulid',
        'customer_id',
        'device_token_hash',
        'device_name',
        'ip_address',
        'last_used_at',
        'last_seen_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'device_token_hash',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * Get the customer that owns the trusted device.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Accessor for last_seen_at with fallback to last_used_at or updated_at.
     */
    public function getLastSeenAtAttribute(?string $value = null): ?Carbon
    {
        if ($value !== null) {
            return Carbon::parse($value);
        }

        return $this->last_used_at ?? $this->updated_at;
    }
}
