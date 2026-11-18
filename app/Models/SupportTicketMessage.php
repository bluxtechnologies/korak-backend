<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\SupportTicketMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $ulid
 * @property int $ticket_id
 * @property string $author_type
 * @property int|null $author_id
 * @property string $author_name
 * @property string $body
 * @property Carbon|null $read_at
 * @property Carbon $created_at
 * @property-read SupportTicket $ticket
 */
class SupportTicketMessage extends Model
{
    /** @use HasFactory<SupportTicketMessageFactory> */
    use HasFactory;

    /**
     * Disable updated_at timestamp.
     *
     * @var string|null
     */
    public const UPDATED_AT = null;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'support_ticket_messages';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'ulid',
        'ticket_id',
        'author_type',
        'author_id',
        'author_name',
        'body',
        'read_at',
        'created_at',
    ];

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::creating(function (SupportTicketMessage $message): void {
            if (empty($message->ulid)) {
                $message->ulid = 'msg_'.strtolower((string) Str::ulid());
            }

            if (empty($message->created_at)) {
                $message->created_at = now();
            }
        });
    }

    /**
     * Get the ticket that this message belongs to.
     *
     * @return BelongsTo<SupportTicket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }
}
