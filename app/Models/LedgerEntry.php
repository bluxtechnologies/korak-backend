<?php

namespace App\Models;

use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryType;
use App\Models\Concerns\HasPublicUlid;
use Carbon\Carbon;
use Database\Factories\LedgerEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property int $id
 * @property string $ulid
 * @property int $wallet_id
 * @property LedgerDirection $direction
 * @property LedgerEntryType $type
 * @property int $amount
 * @property int $balance_after
 * @property string $currency
 * @property string|null $reference
 * @property string $description
 * @property array<string, mixed>|null $metadata
 * @property Carbon $created_at
 * @property-read Wallet $wallet
 */
class LedgerEntry extends Model
{
    /** @use HasFactory<LedgerEntryFactory> */
    use HasFactory;

    use HasPublicUlid;

    /**
     * Disable updated_at timestamp since ledger records are strictly append-only.
     *
     * @var string|null
     */
    public const UPDATED_AT = null;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ledger_entries';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'ulid',
        'wallet_id',
        'direction',
        'type',
        'amount',
        'balance_after',
        'currency',
        'reference',
        'description',
        'metadata',
        'created_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => LedgerDirection::class,
            'type' => LedgerEntryType::class,
            'amount' => 'integer',
            'balance_after' => 'integer',
            'metadata' => 'array',
        ];
    }

    /**
     * Boot the model and enforce append-only immutability per Doc 14 §1.
     */
    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Ledger entries are strictly append-only and cannot be updated.');
        });

        static::deleting(function (): void {
            throw new LogicException('Ledger entries are strictly append-only and cannot be deleted.');
        });
    }

    /**
     * Get the wallet this ledger entry belongs to.
     *
     * @return BelongsTo<Wallet, $this>
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }
}
