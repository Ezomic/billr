<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Invoice;
use App\Models\RecurringInvoice;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class GenerateInvoiceFromSchedule
{
    private const DEFAULT_PAYMENT_TERMS_DAYS = 30;

    public function __construct(
        private readonly AllocateInvoiceNumber $allocateNumber,
    ) {}

    /**
     * Returns null when this period has already been generated. The unique index
     * on (recurring_invoice_id, recurring_period) is the real guard: two runs
     * racing on the same schedule both try to insert, and one loses.
     */
    public function handle(RecurringInvoice $schedule): ?Invoice
    {
        $period = $schedule->next_run_on;

        try {
            return DB::transaction(function () use ($schedule, $period): Invoice {
                $schedule->loadMissing('lines', 'client', 'workspace');

                $invoice = Invoice::create([
                    'workspace_id' => $schedule->workspace_id,
                    'client_id' => $schedule->client_id,
                    'recurring_invoice_id' => $schedule->id,
                    'recurring_period' => $period,
                    'created_by' => $schedule->created_by,
                    'number' => $this->allocateNumber->handle($schedule->workspace_id),
                    'status' => 'draft',
                    'currency' => $schedule->currency,
                    'tax_rate' => $schedule->tax_rate,
                    'notes' => $schedule->notes,
                    'issued_at' => $period,
                    'due_at' => $period->addDays($this->paymentTermsDays($schedule)),
                ]);

                foreach ($schedule->lines as $line) {
                    $invoice->lines()->create([
                        'description' => $line->description,
                        'quantity' => $line->quantity,
                        'unit' => $line->unit,
                        'unit_price' => $line->unit_price,
                        'amount' => $line->amount,
                        'sort_order' => $line->sort_order,
                    ]);
                }

                $invoice->recalculateTotals();

                // Advance from the period just generated, not from today, so a
                // late run does not shift the whole schedule forward.
                $schedule->update(['next_run_on' => $schedule->advance($period)]);

                return $invoice;
            });
        } catch (QueryException) {
            return null;
        }
    }

    private function paymentTermsDays(RecurringInvoice $schedule): int
    {
        $client = $schedule->client;
        $workspace = $schedule->workspace;

        $clientTerms = $client !== null ? $client->payment_terms_days : null;
        $workspaceTerms = $workspace !== null ? $workspace->payment_terms_days : null;

        return $clientTerms
            ?? $workspaceTerms
            ?? self::DEFAULT_PAYMENT_TERMS_DAYS;
    }
}
