<?php

namespace App\Models;

use App\Enums\FundingStatus;
use App\Models\Concerns\HasPublicUlid;
use Carbon\Carbon;
use Database\Factories\FundingRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $ulid
 * @property int $customer_id
 * @property int $wallet_id
 * @property string $provider
 * @property string $reference
 * @property string|null $provider_reference
 * @property int $amount
 * @property int|null $gross_amount
 * @property int $fee
 * @property string $currency
 * @property FundingStatus $status
 * @property string|null $checkout_url
 * @property string|null $sender_name
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $processed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Customer $customer
 * @property-read Wallet $wallet
 */
class FundingRecord extends Model
{
    /** @use HasFactory<FundingRecordFactory> */
    use HasFactory;

    use HasPublicUlid;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'funding_records';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'ulid',
        'customer_id',
        'wallet_id',
        'provider',
        'reference',
        'provider_reference',
        'amount',
        'gross_amount',
        'fee',
        'currency',
        'status',
        'checkout_url',
        'sender_name',
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
            'status' => FundingStatus::class,
            'amount' => 'integer',
            'gross_amount' => 'integer',
            'fee' => 'integer',
            'metadata' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * Get the customer that initiated this funding record.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the wallet credited by this funding record.
     *
     * @return BelongsTo<Wallet, $this>
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }
}
