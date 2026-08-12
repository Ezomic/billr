<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateInvoiceFromTimeEntries;
use App\Concerns\InteractsWithCurrentUser;
use App\Mail\InvoiceSentMail;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\TimeEntry;
use App\Services\InvoicePdfRenderer;
use App\Services\StripeService;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    use InteractsWithCurrentUser;

    public function index(): Response
    {
        $invoices = $this->currentUser()->requireCurrentWorkspace()->invoices()
            ->with('client:id,name')
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('invoices/Index', [
            'invoices' => $invoices,
        ]);
    }

    public function create(): Response
    {
        $workspace = $this->currentUser()->requireCurrentWorkspace();

        $clients = $workspace->clients()
            ->orderBy('name')
            ->get(['id', 'name', 'currency']);

        return Inertia::render('invoices/Create', [
            'clients' => $clients,
        ]);
    }

    public function store(Request $request, CreateInvoiceFromTimeEntries $action): RedirectResponse
    {
        $request->validate([
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'time_entry_ids' => ['array'],
            'time_entry_ids.*' => ['integer', 'exists:time_entries,id'],
            'project_ids' => ['array'],
            'project_ids.*' => ['integer', 'exists:projects,id'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $workspace = $this->currentUser()->requireCurrentWorkspace();
        /** @var Client $client */
        $client = $workspace->clients()->where('id', $request->integer('client_id'))->firstOrFail();

        $toInt = fn (mixed $id): int => is_numeric($id) ? (int) $id : 0;

        $invoice = $action->handle(
            user: $this->currentUser(),
            client: $client,
            timeEntryIds: $request->collect('time_entry_ids')->map($toInt),
            taxRate: $request->float('tax_rate'),
            fixedPriceProjectIds: $request->collect('project_ids')->map($toInt),
        );

        return redirect()->route('invoices.show', $invoice)->with('success', 'Invoice created.');
    }

    public function show(Invoice $invoice): Response
    {
        $this->authorizeInvoice($invoice);

        $invoice->load('client', 'lines', 'createdBy:id,name', 'timeEntries.project:id,name');

        return Inertia::render('invoices/Show', [
            'invoice' => $invoice,
        ]);
    }

    public function downloadPdf(Invoice $invoice, InvoicePdfRenderer $renderer): HttpResponse
    {
        $this->authorizeInvoice($invoice);

        return $renderer->download($invoice);
    }

    public function markSent(Invoice $invoice): RedirectResponse
    {
        $this->authorizeInvoice($invoice);
        abort_if($invoice->status === 'paid', 422);
        abort_if($invoice->status === 'void', 422, 'Cannot send a voided invoice.');

        $invoice->update(['status' => 'sent', 'issued_at' => $invoice->issued_at ?? today()]);

        return back()->with('success', 'Invoice marked as sent.');
    }

    public function markPaid(Invoice $invoice): RedirectResponse
    {
        $this->authorizeInvoice($invoice);
        abort_if($invoice->status === 'void', 422, 'Cannot mark a voided invoice as paid.');

        $invoice->update(['status' => 'paid', 'paid_at' => now()]);

        return back()->with('success', 'Invoice marked as paid.');
    }

    public function markVoid(Invoice $invoice): RedirectResponse
    {
        $this->authorizeInvoice($invoice);
        abort_if($invoice->status === 'paid', 422, 'Cannot void a paid invoice.');
        abort_if($invoice->status === 'void', 422, 'Invoice is already void.');

        DB::transaction(function () use ($invoice): void {
            // Releasing the entries is the point of voiding rather than deleting:
            // the invoice stays on record but the hours become billable again.
            $invoice->timeEntries()->detach();
            $invoice->update(['status' => 'void']);
        });

        return back()->with('success', 'Invoice voided.');
    }

    public function destroy(Invoice $invoice): RedirectResponse
    {
        $this->authorizeInvoice($invoice);
        abort_if(in_array($invoice->status, ['sent', 'paid']), 422, 'Cannot delete a sent or paid invoice.');

        $invoice->delete();

        return redirect()->route('invoices.index')->with('success', 'Invoice deleted.');
    }

    public function unbilledEntries(Request $request): JsonResponse
    {
        $request->validate(['client_id' => ['required', 'integer']]);

        $workspace = $this->currentUser()->requireCurrentWorkspace();
        /** @var Client $client */
        $client = $workspace->clients()->where('id', $request->integer('client_id'))->firstOrFail();

        $entries = TimeEntry::query()
            ->whereHas('project', fn ($q) => $q->where('client_id', $client->id))
            ->whereNotNull('stopped_at')
            ->whereNotExists(fn (QueryBuilder $q) => $q->from('invoice_time_entries')->whereColumn('time_entry_id', 'time_entries.id'))
            ->where('billable', true)
            ->with('project:id,name')
            ->orderByDesc('started_at')
            ->get();

        return response()->json($entries);
    }

    public function unbilledProjects(Request $request): JsonResponse
    {
        $request->validate(['client_id' => ['required', 'integer']]);

        $workspace = $this->currentUser()->requireCurrentWorkspace();
        /** @var Client $client */
        $client = $workspace->clients()->where('id', $request->integer('client_id'))->firstOrFail();

        $projects = $client->projects()
            ->where('type', 'fixed')
            ->whereNotNull('fixed_price')
            ->whereDoesntHave('invoices')
            ->orderBy('name')
            ->get(['id', 'name', 'fixed_price']);

        return response()->json($projects);
    }

    public function update(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorizeInvoice($invoice);
        abort_unless($invoice->status === 'draft', 422, 'Only a draft invoice can be edited.');

        $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
            'issued_at' => ['required', 'date'],
            'due_at' => ['required', 'date', 'after_or_equal:issued_at'],
        ]);

        $notes = $request->input('notes');

        $invoice->update([
            'notes' => is_string($notes) && trim($notes) !== '' ? $notes : null,
            'issued_at' => $request->date('issued_at'),
            'due_at' => $request->date('due_at'),
        ]);

        return back()->with('success', 'Invoice updated.');
    }

    public function storeLine(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorizeInvoice($invoice);
        abort_unless($invoice->status === 'draft', 422, 'Only a draft invoice can be edited.');

        $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit_price' => ['required', 'integer', 'min:0'],
        ]);

        $quantity = $request->integer('quantity');
        $unitPrice = $request->integer('unit_price');
        $highestSort = $invoice->lines()->max('sort_order');

        $invoice->lines()->create([
            'description' => $request->string('description')->toString(),
            'quantity' => $quantity,
            'unit' => 'fixed',
            'unit_price' => $unitPrice,
            'amount' => $quantity * $unitPrice,
            'sort_order' => (is_numeric($highestSort) ? (int) $highestSort : 0) + 1,
        ]);

        $invoice->recalculateTotals();

        return back()->with('success', 'Line added.');
    }

    public function destroyLine(Invoice $invoice, InvoiceLine $line): RedirectResponse
    {
        $this->authorizeInvoice($invoice);
        abort_unless($invoice->status === 'draft', 422, 'Only a draft invoice can be edited.');
        abort_unless($line->invoice_id === $invoice->id, 404);

        $line->delete();

        $invoice->recalculateTotals();

        return back()->with('success', 'Line removed.');
    }

    public function send(Invoice $invoice): RedirectResponse
    {
        $this->authorizeInvoice($invoice);
        abort_if($invoice->status === 'paid', 422, 'Cannot send a paid invoice.');
        abort_if($invoice->status === 'void', 422, 'Cannot send a voided invoice.');
        $clientEmail = $invoice->client?->email;

        abort_if(empty($clientEmail), 422, 'Client has no email address.');

        $invoice->loadMissing('workspace', 'client', 'lines');

        Mail::to($clientEmail)->send(new InvoiceSentMail($invoice));

        if ($invoice->status === 'draft') {
            $invoice->update([
                'status' => 'sent',
                'issued_at' => $invoice->issued_at ?? today(),
            ]);
        }

        return back()->with('success', 'Invoice emailed to '.$clientEmail.'.');
    }

    public function generatePaymentLink(Invoice $invoice, StripeService $stripe): JsonResponse
    {
        $this->authorizeInvoice($invoice);
        abort_if($invoice->status === 'paid', 422, 'Invoice is already paid.');
        abort_if($invoice->status === 'void', 422, 'Cannot take payment on a voided invoice.');

        $url = $stripe->createPaymentLink($invoice);

        $invoice->update(['stripe_payment_link' => $url]);

        return response()->json(['url' => $url]);
    }

    private function authorizeInvoice(Invoice $invoice): void
    {
        abort_unless($invoice->workspace_id === $this->currentUser()->current_workspace_id, 403);
    }
}
