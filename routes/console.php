<?php

use App\Console\Commands\GenerateRecurringInvoices;
use App\Console\Commands\MarkInvoicesOverdue;
use App\Console\Commands\SendInvoiceReminders;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Before the overdue sweep, so a freshly generated invoice is dated correctly
// and never lands already flagged overdue by the same morning's run.
Schedule::command(GenerateRecurringInvoices::class)->dailyAt('05:45');
Schedule::command(MarkInvoicesOverdue::class)->dailyAt('06:00');
// After the overdue sweep, so a freshly overdue invoice is eligible the same morning.
Schedule::command(SendInvoiceReminders::class)->dailyAt('06:15');
