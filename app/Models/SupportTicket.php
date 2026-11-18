<?php

namespace App\Models;

use App\Enums\TicketCategory;
use App\Enums\TicketStatus;
use Carbon\Carbon;
use Database\Factories\SupportTicketFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $ulid
 * @property string $reference
 * @property int $customer_id
 * @property string $subject
 * @property TicketCategory $category
 * @property TicketStatus $status
 * @property bool $has_unread_reply
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Customer $customer
 * @property-read Collection<int, SupportTicketMessage> $messages
 * @property-read SupportTicketMessage|null $latestMessage
 */
class SupportTicket extends Model
{
    /** @use HasFactory<SupportTicketFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'support_tickets';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'ulid',
        'reference',
        'customer_id',
        'subject',
        'category',
        'status',
        'has_unread_reply',
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
            'category' => TicketCategory::class,
            'status' => TicketStatus::class,
            'has_unread_reply' => 'boolean',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::creating(function (SupportTicket $ticket): void {
            if (empty($ticket->ulid)) {
                $ticket->ulid = 'tkt_'.strtolower((string) Str::ulid());
            }

            if (empty($ticket->reference)) {
                /** @var SupportTicket|null $latest */
                $latest = static::query()->latest('id')->first();
                $nextId = $latest ? ($latest->id + 1) : 1;
                $candidate = sprintf('TKT-%05d', $nextId);

                while (static::where('reference', $candidate)->exists()) {
                    $nextId++;
                    $candidate = sprintf('TKT-%05d', $nextId);
                }

                $ticket->reference = $candidate;
            }
        });
    }

    /**
     * Get the customer that owns the ticket.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the messages for the support ticket.
     *
     * @return HasMany<SupportTicketMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class, 'ticket_id');
    }

    /**
     * Get the latest message for the support ticket.
     *
     * @return HasOne<SupportTicketMessage, $this>
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(SupportTicketMessage::class, 'ticket_id')->latestOfMany('id');
    }
}
