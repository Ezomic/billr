<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateInvoiceFromTimeEntries;
use App\Mail\InvoiceSentMail;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\TimeEntry;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function index(): Response
    {
        $invoices = Auth::user()->currentWorkspace->invoices()
            ->with('client:id,name')
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('invoices/Index', [
            'invoices' => $invoices,
        ]);
    }

    public function create(): Response
    {
        $workspace = Auth::user()->currentWorkspace;

        $clients = $workspace->clients()
            ->orderBy('name')
            ->get(['id', 'name', 'currency']);

        return Inertia::render('invoices/Create', [
            'clients' => $clients,
        ]);
    }

    public function store(Request $request, CreateInvoiceFromTimeEntries $action): RedirectResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'time_entry_ids' => ['required', 'array', 'min:1'],
            'time_entry_ids.*' => ['integer', 'exists:time_entries,id'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $workspace = Auth::user()->currentWorkspace;
        /** @var Client $client */
        $client = $workspace->clients()->where('id', $data['client_id'])->firstOrFail();

        /** @var array<int, int> $rawIds */
        $rawIds = $data['time_entry_ids'];
        $ids = collect(array_map('intval', $rawIds));

        $invoice = $action->handle(
            user: Auth::user(),
            client: $client,
            timeEntryIds: $ids,
            taxRate: (float) ($data['tax_rate'] ?? 0),
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

    public function markSent(Invoice $invoice): RedirectResponse
    {
        $this->authorizeInvoice($invoice);
        abort_if($invoice->status === 'paid', 422);

        $invoice->update(['status' => 'sent', 'issued_at' => $invoice->issued_at ?? today()]);

        return back()->with('success', 'Invoice marked as sent.');
    }

    public function markPaid(Invoice $invoice): RedirectResponse
    {
        $this->authorizeInvoice($invoice);

        $invoice->update(['status' => 'paid', 'paid_at' => now()]);

        return back()->with('success', 'Invoice marked as paid.');
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

        $workspace = Auth::user()->currentWorkspace;
        /** @var Client $client */
        $client = $workspace->clients()->where('id', (int) $request->input('client_id'))->firstOrFail();

        $entries = TimeEntry::query()
            ->whereHas('project', fn ($q) => $q->where('client_id', $client->id))
            ->whereNotNull('stopped_at')
            ->whereNotExists(fn ($q) => $q->from('invoice_time_entries')->whereColumn('time_entry_id', 'time_entries.id'))
            ->where('billable', true)
            ->with('project:id,name')
            ->orderByDesc('started_at')
            ->get();

        return response()->json($entries);
    }

    public function send(Invoice $invoice): RedirectResponse
    {
        $this->authorizeInvoice($invoice);
        abort_if($invoice->status === 'paid', 422, 'Cannot send a paid invoice.');
        abort_if(empty($invoice->client->email), 422, 'Client has no email address.');

        $invoice->loadMissing('workspace', 'client', 'lines');

        Mail::to($invoice->client->email)->send(new InvoiceSentMail($invoice));

        if ($invoice->status === 'draft') {
            $invoice->update([
                'status' => 'sent',
                'issued_at' => $invoice->issued_at ?? today(),
            ]);
        }

        return back()->with('success', 'Invoice emailed to '.$invoice->client->email.'.');
    }

    public function generatePaymentLink(Invoice $invoice, StripeService $stripe): JsonResponse
    {
        $this->authorizeInvoice($invoice);
        abort_if($invoice->status === 'paid', 422, 'Invoice is already paid.');

        $url = $stripe->createPaymentLink($invoice);

        $invoice->update(['stripe_payment_link' => $url]);

        return response()->json(['url' => $url]);
    }

    private function authorizeInvoice(Invoice $invoice): void
    {
        abort_unless($invoice->workspace_id === Auth::user()->current_workspace_id, 403);
    }
}
