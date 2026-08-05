<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Invoice;
use App\Services\InvoicePdfRenderer;
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
        $renderer = app(InvoicePdfRenderer::class);
        $pdf = $renderer->render($this->invoice);

        return [
            Attachment::fromData(
                fn () => $pdf->output(),
                $renderer->filename($this->invoice),
            )->withMime('application/pdf'),
        ];
    }
}
