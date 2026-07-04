<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Invoice;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class MarkInvoicesOverdue extends Command
{
    protected $signature = 'invoices:mark-overdue';

    protected $description = 'Flip sent invoices to overdue when their due date has passed';

    public function handle(): int
    {
        $today = CarbonImmutable::today();

        $count = Invoice::query()
            ->where('status', 'sent')
            ->whereDate('due_at', '<', $today)
            ->update(['status' => 'overdue']);

        $this->info("Marked {$count} invoice(s) as overdue.");

        return self::SUCCESS;
    }
}
