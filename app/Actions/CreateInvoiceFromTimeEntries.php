<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Support\Collection;

class CreateInvoiceFromTimeEntries
{
    public function handle(User $user, Client $client, Collection $timeEntryIds, float $taxRate = 0): Invoice
    {
        $workspace = $user->currentWorkspace;

        $entries = TimeEntry::whereIn('id', $timeEntryIds)
            ->whereHas('project', fn ($q) => $q->where('client_id', $client->id))
            ->get();

        $number = $this->nextInvoiceNumber($workspace->id);

        $invoice = Invoice::create([
            'workspace_id' => $workspace->id,
            'client_id'    => $client->id,
            'created_by'   => $user->id,
            'number'       => $number,
            'status'       => 'draft',
            'currency'     => $client->currency ?? $workspace->currency,
            'tax_rate'     => $taxRate,
            'issued_at'    => today(),
            'due_at'       => today()->addDays(30),
        ]);

        $subtotal = 0;
        $sort     = 0;

        foreach ($entries as $entry) {
            $minutes   = $entry->duration_minutes ?? 0;
            $rate      = $entry->hourly_rate ?? $entry->project->hourly_rate ?? 0;
            $amount    = (int) round(($minutes / 60) * $rate);
            $subtotal += $amount;

            $invoice->lines()->create([
                'description' => $entry->description ?? $entry->project->name,
                'quantity'    => $minutes,
                'unit'        => 'hours',
                'unit_price'  => $rate,
                'amount'      => $amount,
                'sort_order'  => $sort++,
            ]);
        }

        $taxAmount = (int) round($subtotal * ($taxRate / 100));
        $total     = $subtotal + $taxAmount;

        $invoice->update([
            'subtotal'   => $subtotal,
            'tax_amount' => $taxAmount,
            'total'      => $total,
        ]);

        $invoice->timeEntries()->attach($entries->pluck('id'));

        return $invoice;
    }

    private function nextInvoiceNumber(int $workspaceId): string
    {
        $year  = now()->year;
        $count = Invoice::where('workspace_id', $workspaceId)
            ->whereYear('created_at', $year)
            ->count() + 1;

        return sprintf('INV-%d-%04d', $year, $count);
    }
}
