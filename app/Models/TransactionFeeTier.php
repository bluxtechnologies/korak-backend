<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\TransactionFeeTierFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $transaction_type
 * @property string $name
 * @property int $min_amount
 * @property int|null $max_amount
 * @property int $fee
 * @property bool $is_active
 * @property int $priority
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read string $min_amount_formatted
 * @property-read string $max_amount_formatted
 * @property-read string $fee_formatted
 *
 * @method static Builder<static> active()
 * @method static Builder<static> forType(string $type)
 */
class TransactionFeeTier extends Model
{
    /** @use HasFactory<TransactionFeeTierFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'transaction_fee_tiers';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'transaction_type',
        'name',
        'min_amount',
        'max_amount',
        'fee',
        'is_active',
        'priority',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_amount' => 'integer',
            'max_amount' => 'integer',
            'fee' => 'integer',
            'is_active' => 'boolean',
            'priority' => 'integer',
        ];
    }

    /**
     * Scope query to only active tiers.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope query to a specific transaction type (e.g. 'deposit').
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForType(Builder $query, string $type): Builder
    {
        return $query->where('transaction_type', $type);
    }

    /**
     * Determine if an amount in kobo matches this fee tier.
     */
    public function matches(int $amountInKobo): bool
    {
        if ($amountInKobo < $this->min_amount) {
            return false;
        }

        if ($this->max_amount !== null && $amountInKobo > $this->max_amount) {
            return false;
        }

        return true;
    }

    /**
     * Human-readable formatted minimum amount.
     */
    public function getMinAmountFormattedAttribute(): string
    {
        return '₦'.number_format($this->min_amount / 100, 2);
    }

    /**
     * Human-readable formatted maximum amount.
     */
    public function getMaxAmountFormattedAttribute(): string
    {
        return $this->max_amount !== null
            ? '₦'.number_format($this->max_amount / 100, 2)
            : 'No limit';
    }

    /**
     * Human-readable formatted fee.
     */
    public function getFeeFormattedAttribute(): string
    {
        return '₦'.number_format($this->fee / 100, 2);
    }
}
