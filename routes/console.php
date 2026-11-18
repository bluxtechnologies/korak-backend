<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Background Tasks & Reconciliation (Doc 14 §7 & Doc 15 §4)
|--------------------------------------------------------------------------
*/

// Reconcile in-flight / processing bill orders every 5 minutes
Schedule::command('bills:reconcile')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Reconcile pending wallet funding records every 10 minutes
Schedule::command('wallet:reconcile-funding')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Verify wallet balance vs append-only ledger consistency daily
Schedule::command('wallet:reconcile')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->runInBackground();
