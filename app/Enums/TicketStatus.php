<?php

namespace App\Enums;

enum TicketStatus: string
{
    case OPEN = 'open';
    case IN_PROGRESS = 'in_progress';
    case RESOLVED = 'resolved';
    case CLOSED = 'closed';

    /**
     * Determine if customer replies are locked for this status.
     */
    public function isLockedForReplies(): bool
    {
        return $this === self::RESOLVED || $this === self::CLOSED;
    }
}
