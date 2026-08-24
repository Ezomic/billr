<?php

declare(strict_types=1);

use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Testing\TestResponse;

const WEBHOOK_SECRET = 'whsec_billr_test_secret';

beforeEach(function () {
    config(['services.stripe.webhook_secret' => WEBHOOK_SECRET]);

    $this->user = User::factory()->create(['type' => 'freelancer']);
    $this->workspace = Workspace::create([
        'name' => 'Test Workspace',
        'slug' => 'test-workspace',
        'owner_id' => $this->user->id,
        'currency' => 'USD',
        'timezone' => 'UTC',
    ]);
    $this->client = Client::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Test Client',
        'currency' => 'USD',
    ]);
    $this->invoice = Invoice::create([
        'workspace_id' => $this->workspace->id,
        'client_id' => $this->client->id,
        'created_by' => $this->user->id,
        'number' => 'INV-2026-0001',
        'status' => 'sent',
        'currency' => 'USD',
        'subtotal' => 10000,
        'tax_amount' => 0,
        'total' => 10000,
        'tax_rate' => 0,
    ]);
});

function stripeEvent(string $type, array $session): string
{
    return (string) json_encode([
        'id' => 'evt_test',
        'object' => 'event',
        'api_version' => '2024-06-20',
        'type' => $type,
        'data' => [
            'object' => array_merge([
                'id' => 'cs_test_123',
                'object' => 'checkout.session',
            ], $session),
        ],
    ]);
}

function postStripeWebhook(string $payload, ?string $secret = null): TestResponse
{
    $timestamp = time();
    $signature = hash_hmac('sha256', $timestamp.'.'.$payload, $secret ?? WEBHOOK_SECRET);

    return test()->call(
        method: 'POST',
        uri: route('stripe.webhook'),
        server: [
            'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.$signature,
            'CONTENT_TYPE' => 'application/json',
        ],
        content: $payload,
    );
}

it('settles the invoice when the checkout session is paid', function () {
    $payload = stripeEvent('checkout.session.completed', [
        'payment_status' => 'paid',
        'amount_total' => 10000,
        'currency' => 'usd',
        'metadata' => ['invoice_id' => (string) $this->invoice->id],
    ]);

    postStripeWebhook($payload)->assertOk();

    expect($this->invoice->fresh()->status)->toBe('paid')
        ->and($this->invoice->fresh()->paid_at)->not->toBeNull()
        ->and($this->invoice->fresh()->stripe_session_id)->toBe('cs_test_123');
});

it('records the settlement as a payment rather than just flipping the status', function () {
    $payload = stripeEvent('checkout.session.completed', [
        'payment_status' => 'paid',
        'amount_total' => 10000,
        'currency' => 'usd',
        'metadata' => ['invoice_id' => (string) $this->invoice->id],
    ]);

    postStripeWebhook($payload)->assertOk();

    $invoice = $this->invoice->fresh();
    $payment = $invoice->payments()->first();

    expect($payment)->not->toBeNull()
        ->and($payment->amount)->toBe(10000)
        ->and($payment->method)->toBe('stripe')
        ->and($payment->stripe_session_id)->toBe('cs_test_123')
        ->and($invoice->balance())->toBe(0);
});

it('does not pay twice when Stripe replays the same session', function () {
    $payload = stripeEvent('checkout.session.completed', [
        'payment_status' => 'paid',
        'amount_total' => 10000,
        'currency' => 'usd',
        'metadata' => ['invoice_id' => (string) $this->invoice->id],
    ]);

    postStripeWebhook($payload)->assertOk();
    postStripeWebhook($payload)->assertOk();

    expect($this->invoice->fresh()->payments()->count())->toBe(1);
});

it('leaves the invoice unpaid when a delayed payment method has not settled', function () {
    $payload = stripeEvent('checkout.session.completed', [
        'payment_status' => 'unpaid',
        'amount_total' => 10000,
        'currency' => 'usd',
        'metadata' => ['invoice_id' => (string) $this->invoice->id],
    ]);

    postStripeWebhook($payload)->assertOk();

    expect($this->invoice->fresh()->status)->toBe('sent')
        ->and($this->invoice->fresh()->paid_at)->toBeNull();
});

it('settles the invoice when the delayed payment later succeeds', function () {
    $payload = stripeEvent('checkout.session.async_payment_succeeded', [
        'payment_status' => 'paid',
        'amount_total' => 10000,
        'currency' => 'usd',
        'metadata' => ['invoice_id' => (string) $this->invoice->id],
    ]);

    postStripeWebhook($payload)->assertOk();

    expect($this->invoice->fresh()->status)->toBe('paid');
});

it('leaves the invoice unpaid when the delayed payment fails', function () {
    $payload = stripeEvent('checkout.session.async_payment_failed', [
        'payment_status' => 'unpaid',
        'amount_total' => 10000,
        'currency' => 'usd',
        'metadata' => ['invoice_id' => (string) $this->invoice->id],
    ]);

    postStripeWebhook($payload)->assertOk();

    expect($this->invoice->fresh()->status)->toBe('sent');
});

it('refuses to settle when the paid amount does not match the invoice', function () {
    $payload = stripeEvent('checkout.session.completed', [
        'payment_status' => 'paid',
        'amount_total' => 100,
        'currency' => 'usd',
        'metadata' => ['invoice_id' => (string) $this->invoice->id],
    ]);

    postStripeWebhook($payload)->assertOk();

    expect($this->invoice->fresh()->status)->toBe('sent');
});

it('refuses to settle when the currency does not match the invoice', function () {
    $payload = stripeEvent('checkout.session.completed', [
        'payment_status' => 'paid',
        'amount_total' => 10000,
        'currency' => 'eur',
        'metadata' => ['invoice_id' => (string) $this->invoice->id],
    ]);

    postStripeWebhook($payload)->assertOk();

    expect($this->invoice->fresh()->status)->toBe('sent');
});

it('rejects a payload that is not signed with the webhook secret', function () {
    $payload = stripeEvent('checkout.session.completed', [
        'payment_status' => 'paid',
        'amount_total' => 10000,
        'currency' => 'usd',
        'metadata' => ['invoice_id' => (string) $this->invoice->id],
    ]);

    postStripeWebhook($payload, 'whsec_wrong_secret')->assertStatus(400);

    expect($this->invoice->fresh()->status)->toBe('sent');
});

it('ignores an unrelated event type', function () {
    $payload = stripeEvent('payment_intent.created', [
        'payment_status' => 'paid',
        'amount_total' => 10000,
        'currency' => 'usd',
        'metadata' => ['invoice_id' => (string) $this->invoice->id],
    ]);

    postStripeWebhook($payload)->assertOk();

    expect($this->invoice->fresh()->status)->toBe('sent');
});
