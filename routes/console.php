<?php

use App\Console\Commands\MarkInvoicesOverdue;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(MarkInvoicesOverdue::class)->dailyAt('06:00');
