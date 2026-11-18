<?php

namespace App\Enums;

enum TicketCategory: string
{
    case BILLING = 'billing';
    case ORDER = 'order';
    case VPN = 'vpn';
    case AIRTIME_DATA = 'airtime_data';
    case ACCOUNT = 'account';
    case GENERAL = 'general';
}
