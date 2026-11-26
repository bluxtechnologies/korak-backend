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
 * @property string $reference
 * @property string|null $provider_reference
 * @property string $category
 * @property string $service_code
 * @property string $service_name
 * @property string|null $package_code
 * @property string $customer_identifier
 * @property string|null $customer_name
 * @property int $amount
 * @property int $face_amount
 * @property int $discount_amount
 * @property string $currency
 * @property string $status
 * @property string|null $token
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $processed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Customer $customer
 */
class BillOrder extends Model
{
    use HasFactory;
    use HasPublicUlid;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'bill_orders';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'ulid',
        'customer_id',
        'reference',
        'provider_reference',
        'category',
        'service_code',
        'service_name',
        'package_code',
        'customer_identifier',
        'customer_name',
        'amount',
        'face_amount',
        'discount_amount',
        'currency',
        'status',
        'token',
        'metadata',
        'processed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'face_amount' => 'integer',
            'discount_amount' => 'integer',
            'metadata' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * Customer who placed this bill order.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Compatibility accessor for type / category.
     */
    public function getTypeAttribute(): string
    {
        return (string) ($this->attributes['category'] ?? '');
    }
}
