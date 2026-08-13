<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Concerns\InteractsWithCurrentUser;
use App\Http\Requests\RecurringInvoice\UpsertRecurringInvoiceRequest;
use App\Models\Client;
use App\Models\RecurringInvoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RecurringInvoiceController extends Controller
{
    use InteractsWithCurrentUser;

    public function index(): Response
    {
        $workspace = $this->currentUser()->requireCurrentWorkspace();

        $schedules = $workspace->recurringInvoices()
            ->with('client:id,name', 'lines')
            ->withCount('invoices')
            ->orderBy('status')
            ->orderBy('next_run_on')
            ->get();

        return Inertia::render('recurring/Index', [
            'schedules' => $schedules,
            'clients' => $workspace->clients()->orderBy('name')->get(['id', 'name', 'currency']),
            'intervals' => RecurringInvoice::INTERVALS,
        ]);
    }

    public function store(UpsertRecurringInvoiceRequest $request): RedirectResponse
    {
        $workspace = $this->currentUser()->requireCurrentWorkspace();

        /** @var Client $client */
        $client = $workspace->clients()->where('id', $request->integer('client_id'))->firstOrFail();

        DB::transaction(function () use ($request, $workspace, $client): void {
            $schedule = $workspace->recurringInvoices()->create([
                'client_id' => $client->id,
                'created_by' => $this->currentUser()->id,
                'name' => $request->string('name')->toString(),
                'interval' => $request->string('interval')->toString(),
                'start_on' => $request->date('start_on'),
                'end_on' => $request->date('end_on'),
                'next_run_on' => $request->date('start_on'),
                'currency' => $client->currency ?? $workspace->currency,
                'tax_rate' => $request->float('tax_rate'),
                'notes' => $request->input('notes'),
                'status' => 'active',
            ]);

            $this->syncLines($schedule, $request);
            $schedule->update(['next_run_on' => $schedule->firstRunOnOrAfterToday()]);
        });

        return back()->with('success', 'Recurring invoice created.');
    }

    public function update(UpsertRecurringInvoiceRequest $request, RecurringInvoice $recurringInvoice): RedirectResponse
    {
        $this->authorizeSchedule($recurringInvoice);

        $workspace = $this->currentUser()->requireCurrentWorkspace();
        /** @var Client $client */
        $client = $workspace->clients()->where('id', $request->integer('client_id'))->firstOrFail();

        DB::transaction(function () use ($request, $recurringInvoice, $client): void {
            $recurringInvoice->update([
                'client_id' => $client->id,
                'name' => $request->string('name')->toString(),
                'interval' => $request->string('interval')->toString(),
                'start_on' => $request->date('start_on'),
                'end_on' => $request->date('end_on'),
                'tax_rate' => $request->float('tax_rate'),
                'notes' => $request->input('notes'),
            ]);

            $recurringInvoice->lines()->delete();
            $this->syncLines($recurringInvoice, $request);

            // Interval or start date may have moved, so the next run has to be
            // recomputed rather than left pointing at a stale date.
            $recurringInvoice->update(['next_run_on' => $recurringInvoice->firstRunOnOrAfterToday()]);
        });

        return back()->with('success', 'Recurring invoice updated.');
    }

    public function pause(RecurringInvoice $recurringInvoice): RedirectResponse
    {
        $this->authorizeSchedule($recurringInvoice);

        $recurringInvoice->update(['status' => 'paused']);

        return back()->with('success', 'Recurring invoice paused.');
    }

    public function resume(RecurringInvoice $recurringInvoice): RedirectResponse
    {
        $this->authorizeSchedule($recurringInvoice);

        $recurringInvoice->update([
            'status' => 'active',
            'next_run_on' => $recurringInvoice->firstRunOnOrAfterToday(),
        ]);

        return back()->with('success', 'Recurring invoice resumed.');
    }

    public function destroy(RecurringInvoice $recurringInvoice): RedirectResponse
    {
        $this->authorizeSchedule($recurringInvoice);

        $recurringInvoice->delete();

        return back()->with('success', 'Recurring invoice deleted.');
    }

    private function syncLines(RecurringInvoice $schedule, UpsertRecurringInvoiceRequest $request): void
    {
        $sort = 0;

        /** @var array<int, array{description: string, quantity: int, unit_price: int}> $lines */
        $lines = $request->validated('lines');

        foreach ($lines as $line) {
            $quantity = (int) $line['quantity'];
            $unitPrice = (int) $line['unit_price'];

            $schedule->lines()->create([
                'description' => $line['description'],
                'quantity' => $quantity,
                'unit' => 'fixed',
                'unit_price' => $unitPrice,
                'amount' => $quantity * $unitPrice,
                'sort_order' => $sort++,
            ]);
        }
    }

    private function authorizeSchedule(RecurringInvoice $schedule): void
    {
        abort_unless($schedule->workspace_id === $this->currentUser()->current_workspace_id, 403);
    }
}
