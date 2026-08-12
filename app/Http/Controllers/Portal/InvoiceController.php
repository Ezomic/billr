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

        $invoice->load('client:id,name,email,vat_number', 'lines', 'workspace:id,name,currency');

        // Built explicitly rather than serialising the model: the client has no
        // use for stripe_session_id and the rest of the internal columns, and the
        // pay link must be gated here rather than only hidden in the template.
        return Inertia::render('portal/Invoice', [
            'invoice' => [
                ...$invoice->only([
                    'id', 'number', 'status', 'currency', 'subtotal', 'tax_amount',
                    'tax_rate', 'total', 'notes', 'issued_at', 'due_at', 'paid_at',
                ]),
                'client' => $invoice->client?->only(['name', 'email', 'vat_number']),
                'workspace' => $invoice->workspace?->only(['name']),
                'lines' => $invoice->lines->map(fn ($line) => $line->only([
                    'id', 'description', 'quantity', 'unit', 'unit_price', 'amount',
                ]))->all(),
                'payment_url' => $this->payableUrl($invoice),
            ],
        ]);
    }

    private function payableUrl(Invoice $invoice): ?string
    {
        return in_array($invoice->status, ['paid', 'void'], true)
            ? null
            : $invoice->stripe_payment_link;
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
