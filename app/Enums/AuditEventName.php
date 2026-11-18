<?php

namespace App\Enums;

enum AuditEventName: string
{
    // Authentication events (Doc 12 §2)
    case AUTH_REGISTERED = 'auth.registered';
    case AUTH_LOGIN_SUCCESS = 'auth.login_success';
    case AUTH_LOGIN_FAILED = 'auth.login_failed';
    case AUTH_LOCKOUT = 'auth.lockout';
    case AUTH_LOGOUT = 'auth.logout';
    case AUTH_PASSWORD_RESET_REQUESTED = 'auth.password_reset_requested';
    case AUTH_PASSWORD_RESET_COMPLETED = 'auth.password_reset_completed';
    case AUTH_PASSWORD_CHANGED = 'auth.password_changed';

    // Device events (Doc 12 §2)
    case DEVICE_CHALLENGE_ISSUED = 'device.challenge_issued';
    case DEVICE_VERIFICATION_SUCCESS = 'device.verification_success';
    case DEVICE_VERIFICATION_FAILED = 'device.verification_failed';
    case DEVICE_TRUSTED = 'device.trusted';
    case DEVICE_REVOKED = 'device.revoked';
    case DEVICE_REVOKED_ALL = 'device.revoked_all';

    // Profile & Session events (Doc 12 §2 & Roadmap B11)
    case PROFILE_UPDATED = 'profile.updated';
    case SESSION_REVOKED = 'session.revoked';
    case SESSION_REVOKED_OTHER = 'session.revoked_other';

    // Money, Ledger & Reconciliation events (Doc 12 §2, Doc 14 §7 & Roadmap B14/B16)
    case BILL_RECONCILED = 'bill.reconciled';
    case FUNDING_RECONCILED = 'funding.reconciled';
    case WALLET_INTEGRITY_MISMATCH = 'wallet.integrity_mismatch';
}
