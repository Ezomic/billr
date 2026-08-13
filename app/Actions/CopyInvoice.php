<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Invoice;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class CopyInvoice
{
    private const DEFAULT_PAYMENT_TERMS_DAYS = 30;

    public function handle(User $user, Invoice $source): Invoice
    {
        return DB::transaction(function () use ($user, $source): Invoice {
            $workspace = $user->requireCurrentWorkspace();
            $source->loadMissing('lines', 'client');

            $copy = Invoice::create([
                'workspace_id' => $workspace->id,
                'client_id' => $source->client_id,
                'created_by' => $user->id,
                'number' => app(AllocateInvoiceNumber::class)->handle($workspace->id),
                'status' => 'draft',
                'currency' => $source->currency,
                'tax_rate' => $source->tax_rate,
                'notes' => $source->notes,
                'issued_at' => today(),
                'due_at' => today()->addDays($this->paymentTermsDays($source, $workspace)),
            ]);

            foreach ($source->lines as $line) {
                $copy->lines()->create([
                    'description' => $line->description,
                    'quantity' => $line->quantity,
                    'unit' => $line->unit,
                    'unit_price' => $line->unit_price,
                    'amount' => $line->amount,
                    'sort_order' => $line->sort_order,
                ]);
            }

            // Deliberately no timeEntries()/projects() attach. Those pivots are
            // what stop the same hours or the same fixed fee being billed twice
            // (BILLR-33, BILLR-38). A copy is a fresh manual charge, not a second
            // claim on the source invoice's billable work.
            $copy->recalculateTotals();

            return $copy;
        });
    }

    private function paymentTermsDays(Invoice $source, Workspace $workspace): int
    {
        $client = $source->client;
        $clientTerms = $client !== null ? $client->payment_terms_days : null;

        return $clientTerms
            ?? $workspace->payment_terms_days
            ?? self::DEFAULT_PAYMENT_TERMS_DAYS;
    }
}
