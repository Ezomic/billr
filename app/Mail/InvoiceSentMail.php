<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceSentMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Invoice $invoice,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Invoice '.$this->invoice->number.' from '.($this->invoice->workspace->name ?? ''),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoice-sent',
            with: [
                'invoice' => $this->invoice,
            ],
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        $this->invoice->loadMissing('workspace', 'client', 'lines');

        $pdf = Pdf::loadView('pdf.invoice', ['invoice' => $this->invoice]);

        return [
            Attachment::fromData(
                fn () => $pdf->output(),
                $this->invoice->number.'.pdf',
            )->withMime('application/pdf'),
        ];
    }
}
