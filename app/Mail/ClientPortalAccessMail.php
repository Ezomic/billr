<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClientPortalAccessMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Client $client,
        public readonly string $token,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Review and approve your time entries',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.client-portal-access',
            with: [
                'client' => $this->client,
                'url' => route('client-portal.show', $this->token),
            ],
        );
    }
}
