<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $event_reference
 * @property string $provider
 * @property string $event_type
 * @property array<string, mixed> $payload
 * @property string $status
 * @property string|null $failure_reason
 * @property int|null $funding_record_id
 * @property Carbon|null $processed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read FundingRecord|null $fundingRecord
 */
class FundingWebhookEvent extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'funding_webhook_events';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'event_reference',
        'provider',
        'event_type',
        'payload',
        'status',
        'failure_reason',
        'funding_record_id',
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
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * Get the funding record associated with this webhook event.
     *
     * @return BelongsTo<FundingRecord, $this>
     */
    public function fundingRecord(): BelongsTo
    {
        return $this->belongsTo(FundingRecord::class);
    }
}
