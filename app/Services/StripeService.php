<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Invoice;
use Stripe\Event;
use Stripe\StripeClient;
use Stripe\Webhook;

class StripeService
{
    private ?StripeClient $client = null;

    // Built on demand: webhook verification needs only the signing secret, and
    // the client constructor rejects an empty API key.
    private function client(): StripeClient
    {
        if ($this->client === null) {
            $key = config('services.stripe.key');
            $this->client = new StripeClient(is_string($key) ? $key : '');
        }

        return $this->client;
    }

    public function createPaymentLink(Invoice $invoice): string
    {
        $price = $this->client()->prices->create([
            'unit_amount' => $invoice->total,
            'currency' => strtolower($invoice->currency),
            'product_data' => [
                'name' => 'Invoice '.$invoice->number,
            ],
        ]);

        $paymentLink = $this->client()->paymentLinks->create([
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
        $secret = config('services.stripe.webhook_secret');

        return Webhook::constructEvent(
            payload: $payload,
            sigHeader: $sig,
            secret: is_string($secret) ? $secret : '',
        );
    }
}
