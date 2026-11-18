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
 * @property int $wallet_id
 * @property string $provider
 * @property string|null $provider_account_id
 * @property string $account_reference
 * @property string $bank_name
 * @property string|null $bank_code
 * @property string $account_number
 * @property string $account_name
 * @property string $status
 * @property array<string, mixed>|null $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Customer $customer
 * @property-read Wallet $wallet
 */
class CustomerVirtualAccount extends Model
{
    use HasFactory;
    use HasPublicUlid;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'customer_virtual_accounts';

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
        'provider_account_id',
        'account_reference',
        'bank_name',
        'bank_code',
        'account_number',
        'account_name',
        'status',
        'metadata',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    /**
     * Get the customer that owns this virtual account.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the wallet associated with this virtual account.
     *
     * @return BelongsTo<Wallet, $this>
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }
}
