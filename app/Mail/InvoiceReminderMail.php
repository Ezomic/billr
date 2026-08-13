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

class InvoiceReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Invoice $invoice,
        public readonly int $daysOverdue,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reminder: invoice '.$this->invoice->number.' is overdue',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoice-reminder',
            with: [
                'invoice' => $this->invoice,
                'daysOverdue' => $this->daysOverdue,
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
