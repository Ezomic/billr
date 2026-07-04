<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Invoice;
use Stripe\Event;
use Stripe\StripeClient;
use Stripe\Webhook;

class StripeService
{
    private StripeClient $client;

    public function __construct()
    {
        $this->client = new StripeClient((string) config('services.stripe.key'));
    }

    public function createPaymentLink(Invoice $invoice): string
    {
        $price = $this->client->prices->create([
            'unit_amount' => $invoice->total,
            'currency' => strtolower($invoice->currency),
            'product_data' => [
                'name' => 'Invoice '.$invoice->number,
            ],
        ]);

        $paymentLink = $this->client->paymentLinks->create([
            'line_items' => [
                ['price' => $price->id, 'quantity' => 1],
            ],
            'metadata' => [
                'invoice_id' => (string) $invoice->id,
            ],
        ]);

        return $paymentLink->url;
    }

    public function constructWebhookEvent(string $payload, string $sig): Event
    {
        return Webhook::constructEvent(
            payload: $payload,
            sigHeader: $sig,
            secret: (string) config('services.stripe.webhook_secret'),
        );
    }
}
