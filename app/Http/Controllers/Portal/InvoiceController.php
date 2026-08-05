<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Concerns\InteractsWithCurrentUser;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\InvoicePdfRenderer;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    use InteractsWithCurrentUser;

    public function show(Invoice $invoice): Response
    {
        $this->authorizeInvoice($invoice);

        $invoice->load('client', 'lines', 'workspace:id,name,currency');

        return Inertia::render('portal/Invoice', [
            'invoice' => $invoice,
        ]);
    }

    public function downloadPdf(Invoice $invoice, InvoicePdfRenderer $renderer): HttpResponse
    {
        $this->authorizeInvoice($invoice);

        return $renderer->download($invoice);
    }

    private function authorizeInvoice(Invoice $invoice): void
    {
        $hasAccess = $invoice->client?->portalUsers()
            ->where('user_id', $this->currentUser()->id)
            ->exists() ?? false;

        abort_unless($hasAccess, 403);
    }
}
