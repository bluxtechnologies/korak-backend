<?php

namespace App\Enums;

enum LedgerEntryType: string
{
    case FUNDING = 'funding';
    case ORDER_DEBIT = 'order_debit';
    case REFUND = 'refund';
    case MANUAL_ADJUSTMENT = 'manual_adjustment';
}
