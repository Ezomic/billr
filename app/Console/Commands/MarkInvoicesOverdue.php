<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Invoice;
use Illuminate\Console\Command;

class MarkInvoicesOverdue extends Command
{
    protected $signature = 'invoices:mark-overdue';

    protected $description = 'Mark sent invoices past their due date as overdue';

    public function handle(): int
    {
        $count = Invoice::query()
            ->whereIn('status', ['sent'])
            ->whereNotNull('due_at')
            ->where('due_at', '<', today())
            ->update(['status' => 'overdue']);

        $this->info("Marked {$count} invoice(s) as overdue.");

        return self::SUCCESS;
    }
}
