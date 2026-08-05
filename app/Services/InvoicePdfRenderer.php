<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as RenderedPdf;
use Illuminate\Http\Response;

class InvoicePdfRenderer
{
    public function render(Invoice $invoice): RenderedPdf
    {
        $invoice->loadMissing('workspace', 'client', 'lines');

        return Pdf::loadView('pdf.invoice', ['invoice' => $invoice]);
    }

    public function download(Invoice $invoice): Response
    {
        return $this->render($invoice)->download($this->filename($invoice));
    }

    public function filename(Invoice $invoice): string
    {
        return $invoice->number.'.pdf';
    }
}
