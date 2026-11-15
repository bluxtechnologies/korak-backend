<?php

namespace App\Models;

use App\Enums\AuditEventName;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * @property int $id
 * @property Carbon $occurred_at
 * @property string|null $actor_type
 * @property int|null $actor_id
 * @property AuditEventName $event
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $request_id
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property-read Model|null $actor
 * @property-read Model|null $subject
 */
class AuditEvent extends Model
{
    use HasFactory;

    /**
     * Disable updated_at timestamp since audit records are append-only.
     *
     * @var string|null
     */
    public const UPDATED_AT = null;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'audit_events';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'occurred_at',
        'actor_type',
        'actor_id',
        'event',
        'subject_type',
        'subject_id',
        'ip_address',
        'user_agent',
        'request_id',
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
            'occurred_at' => 'datetime',
            'event' => AuditEventName::class,
            'metadata' => 'array',
        ];
    }

    /**
     * Boot the model and enforce append-only immutability per Doc 12 §4.
     */
    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Audit events are append-only and cannot be updated.');
        });

        static::deleting(function (): void {
            throw new LogicException('Audit events are append-only and cannot be deleted.');
        });
    }

    /**
     * Get the polymorphic actor responsible for the audit event.
     *
     * @return MorphTo<Model, $this>
     */
    public function actor(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the polymorphic subject entity affected by the audit event.
     *
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
