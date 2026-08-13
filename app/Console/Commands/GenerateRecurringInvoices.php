<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\GenerateInvoiceFromSchedule;
use App\Models\RecurringInvoice;
use Illuminate\Console\Command;

class GenerateRecurringInvoices extends Command
{
    protected $signature = 'invoices:generate-recurring {--dry-run : List what would be generated without creating anything}';

    protected $description = 'Turn due recurring schedules into draft invoices';

    public function handle(GenerateInvoiceFromSchedule $action): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $generated = 0;
        $skipped = 0;

        $schedules = RecurringInvoice::query()
            ->where('status', 'active')
            ->whereDate('next_run_on', '<=', today())
            ->with('lines', 'client', 'workspace')
            ->lazy();

        foreach ($schedules as $schedule) {
            if ($schedule->hasFinished()) {
                $skipped++;
                $this->line("Skipped {$schedule->name}: schedule ended {$schedule->end_on?->toDateString()}.");

                continue;
            }

            if ($schedule->lines->isEmpty()) {
                $skipped++;
                $this->line("Skipped {$schedule->name}: no lines to bill.");

                continue;
            }

            if ($dryRun) {
                $this->line("Would generate {$schedule->name} for {$schedule->next_run_on->toDateString()}.");
                $generated++;

                continue;
            }

            $invoice = $action->handle($schedule);

            if ($invoice === null) {
                $skipped++;
                $this->line("Skipped {$schedule->name}: {$schedule->next_run_on->toDateString()} already generated.");

                continue;
            }

            $generated++;
            $this->line("Generated {$invoice->number} for {$schedule->name}.");
        }

        $this->info($dryRun
            ? "{$generated} invoice(s) would be generated, {$skipped} skipped."
            : "Generated {$generated} invoice(s), skipped {$skipped}.");

        return self::SUCCESS;
    }
}
