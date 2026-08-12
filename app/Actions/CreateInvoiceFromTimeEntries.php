<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateInvoiceFromTimeEntries
{
    private const DEFAULT_PAYMENT_TERMS_DAYS = 30;

    /**
     * @param  Collection<int, int>  $timeEntryIds
     * @param  Collection<int, int>|null  $fixedPriceProjectIds
     */
    public function handle(
        User $user,
        Client $client,
        Collection $timeEntryIds,
        float $taxRate = 0,
        ?Collection $fixedPriceProjectIds = null,
    ): Invoice {
        // Number allocation and insert have to be atomic, or two concurrent
        // creations in the same workspace read the same sequence.
        return DB::transaction(fn (): Invoice => $this->build(
            $user,
            $client,
            $timeEntryIds,
            $taxRate,
            $fixedPriceProjectIds ?? collect(),
        ));
    }

    /**
     * @param  Collection<int, int>  $timeEntryIds
     * @param  Collection<int, int>  $fixedPriceProjectIds
     */
    private function build(
        User $user,
        Client $client,
        Collection $timeEntryIds,
        float $taxRate,
        Collection $fixedPriceProjectIds,
    ): Invoice {
        $workspace = $user->requireCurrentWorkspace();

        $entries = $this->billableEntries($client, $timeEntryIds, (bool) $workspace->require_client_approval);
        $projects = $this->billableFixedPriceProjects($client, $fixedPriceProjectIds);

        if ($entries->isEmpty() && $projects->isEmpty()) {
            throw ValidationException::withMessages([
                'time_entry_ids' => 'Nothing on this invoice can be billed. The selected time entries and fixed-price projects may already be invoiced, still running, or awaiting client approval.',
            ]);
        }

        $invoice = Invoice::create([
            'workspace_id' => $workspace->id,
            'client_id' => $client->id,
            'created_by' => $user->id,
            'number' => $this->nextInvoiceNumber($workspace->id),
            'status' => 'draft',
            'currency' => $client->currency ?? $workspace->currency,
            'tax_rate' => $taxRate,
            'issued_at' => today(),
            'due_at' => today()->addDays($this->paymentTermsDays($client, $workspace)),
        ]);

        $sort = 0;

        foreach ($entries as $entry) {
            $minutes = $entry->duration_minutes ?? 0;
            $rate = $entry->hourly_rate ?? $entry->project->hourly_rate ?? 0;

            $invoice->lines()->create([
                'description' => $entry->description ?? $entry->project->name ?? '',
                'quantity' => $minutes,
                'unit' => 'hours',
                'unit_price' => $rate,
                'amount' => (int) round(($minutes / 60) * $rate),
                'sort_order' => $sort++,
            ]);
        }

        foreach ($projects as $project) {
            $price = $project->fixed_price ?? 0;

            $invoice->lines()->create([
                'description' => $project->name,
                'quantity' => 1,
                'unit' => 'fixed',
                'unit_price' => $price,
                'amount' => $price,
                'sort_order' => $sort++,
            ]);
        }

        $invoice->timeEntries()->attach($entries->pluck('id'));
        $invoice->projects()->attach($projects->pluck('id'));

        $invoice->recalculateTotals();

        return $invoice;
    }

    /**
     * @param  Collection<int, int>  $timeEntryIds
     * @return EloquentCollection<int, TimeEntry>
     */
    private function billableEntries(Client $client, Collection $timeEntryIds, bool $requireApproval): EloquentCollection
    {
        if ($timeEntryIds->isEmpty()) {
            return new EloquentCollection;
        }

        // These filters have to live here rather than only in the picker query,
        // or a replayed request bills the same hours onto a second invoice.
        $query = TimeEntry::whereIn('id', $timeEntryIds)
            ->whereHas('project', fn ($q) => $q->where('client_id', $client->id))
            ->whereNotNull('stopped_at')
            ->where('billable', true)
            ->whereDoesntHave('invoices');

        if ($requireApproval) {
            $query->where('client_approved', true);
        }

        return $query->get();
    }

    /**
     * @param  Collection<int, int>  $projectIds
     * @return EloquentCollection<int, Project>
     */
    private function billableFixedPriceProjects(Client $client, Collection $projectIds): EloquentCollection
    {
        if ($projectIds->isEmpty()) {
            return new EloquentCollection;
        }

        return Project::whereIn('id', $projectIds)
            ->where('client_id', $client->id)
            ->where('type', 'fixed')
            ->whereNotNull('fixed_price')
            ->whereDoesntHave('invoices')
            ->get();
    }

    private function paymentTermsDays(Client $client, Workspace $workspace): int
    {
        return $client->payment_terms_days
            ?? $workspace->payment_terms_days
            ?? self::DEFAULT_PAYMENT_TERMS_DAYS;
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
