<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateInvoiceFromTimeEntries
{
    /** @param Collection<int, int> $timeEntryIds */
    public function handle(User $user, Client $client, Collection $timeEntryIds, float $taxRate = 0): Invoice
    {
        // Number allocation and insert have to be atomic, or two concurrent
        // creations in the same workspace read the same sequence.
        return DB::transaction(fn (): Invoice => $this->build($user, $client, $timeEntryIds, $taxRate));
    }

    /** @param Collection<int, int> $timeEntryIds */
    private function build(User $user, Client $client, Collection $timeEntryIds, float $taxRate): Invoice
    {
        $workspace = $user->requireCurrentWorkspace();

        // These filters have to live here rather than only in the picker query,
        // or a replayed request bills the same hours onto a second invoice.
        $query = TimeEntry::whereIn('id', $timeEntryIds)
            ->whereHas('project', fn ($q) => $q->where('client_id', $client->id))
            ->whereNotNull('stopped_at')
            ->where('billable', true)
            ->whereDoesntHave('invoices');

        if ($workspace->require_client_approval) {
            $query->where('client_approved', true);
        }

        $entries = $query->get();

        if ($entries->isEmpty()) {
            throw ValidationException::withMessages([
                'time_entry_ids' => 'None of the selected time entries can be invoiced. They may already be billed, still running, or awaiting client approval.',
            ]);
        }

        $number = $this->nextInvoiceNumber($workspace->id);

        $invoice = Invoice::create([
            'workspace_id' => $workspace->id,
            'client_id' => $client->id,
            'created_by' => $user->id,
            'number' => $number,
            'status' => 'draft',
            'currency' => $client->currency ?? $workspace->currency,
            'tax_rate' => $taxRate,
            'issued_at' => today(),
            'due_at' => today()->addDays(30),
        ]);

        $subtotal = 0;
        $sort = 0;

        foreach ($entries as $entry) {
            $minutes = $entry->duration_minutes ?? 0;
            $rate = $entry->hourly_rate ?? $entry->project->hourly_rate ?? 0;
            $amount = (int) round(($minutes / 60) * $rate);
            $subtotal += $amount;

            $invoice->lines()->create([
                'description' => $entry->description ?? $entry->project->name ?? '',
                'quantity' => $minutes,
                'unit' => 'hours',
                'unit_price' => $rate,
                'amount' => $amount,
                'sort_order' => $sort++,
            ]);
        }

        $taxAmount = (int) round($subtotal * ($taxRate / 100));
        $total = $subtotal + $taxAmount;

        $invoice->update([
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => $total,
        ]);

        $invoice->timeEntries()->attach($entries->pluck('id'));

        return $invoice;
    }

    // Counting rows would skip soft-deleted invoices while their numbers stay in
    // the unique index, so the sequence is read off the highest number instead.
    private function nextInvoiceNumber(int $workspaceId): string
    {
        $prefix = sprintf('INV-%d-', now()->year);

        $last = Invoice::withTrashed()
            ->where('workspace_id', $workspaceId)
            ->where('number', 'like', $prefix.'%')
            ->orderByRaw('LENGTH(number) desc, number desc')
            ->lockForUpdate()
            ->value('number');

        $sequence = is_string($last)
            ? ((int) substr($last, strlen($prefix))) + 1
            : 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
