<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateInvoiceFromTimeEntries;
use App\Models\Invoice;
use App\Models\TimeEntry;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Auth;
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
        $client = $workspace->clients()->findOrFail($data['client_id']);

        $invoice = $action->handle(
            user: Auth::user(),
            client: $client,
            timeEntryIds: collect($data['time_entry_ids']),
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
        $client = $workspace->clients()->findOrFail($request->client_id);

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

    public function pdf(Invoice $invoice): HttpResponse
    {
        $this->authorizeInvoice($invoice);

        $invoice->load('workspace', 'client', 'lines');

        $pdf = Pdf::loadView('pdf.invoice', ['invoice' => $invoice]);

        return $pdf->download(str_replace('/', '-', $invoice->number).'.pdf');
    }

    private function authorizeInvoice(Invoice $invoice): void
    {
        abort_unless($invoice->workspace_id === Auth::user()->current_workspace_id, 403);
    }
}
