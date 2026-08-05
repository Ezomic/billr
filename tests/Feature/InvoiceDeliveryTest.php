<?php

declare(strict_types=1);

use App\Mail\InvoiceSentMail;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use App\Models\Workspace;
use App\Services\StripeService;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();

    $this->user = User::factory()->create(['type' => 'freelancer']);
    $this->workspace = Workspace::create([
        'name' => 'Test Workspace',
        'slug' => 'test-workspace',
        'owner_id' => $this->user->id,
        'currency' => 'USD',
        'timezone' => 'UTC',
    ]);
    $this->workspace->members()->attach($this->user->id, ['role' => 'owner']);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);

    $this->client = Client::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Test Client',
        'email' => 'billing@client.example',
        'currency' => 'USD',
    ]);

    $this->invoice = Invoice::create([
        'workspace_id' => $this->workspace->id,
        'client_id' => $this->client->id,
        'created_by' => $this->user->id,
        'number' => 'INV-2026-0001',
        'status' => 'draft',
        'currency' => 'USD',
        'subtotal' => 10000,
        'tax_amount' => 0,
        'total' => 10000,
        'tax_rate' => 0,
    ]);

    $this->actingAs($this->user);
});

it('emails the invoice to the client and marks it sent', function () {
    $this->post(route('invoices.send', $this->invoice))->assertRedirect();

    Mail::assertSent(InvoiceSentMail::class, fn ($mail) => $mail->hasTo('billing@client.example')
        && $mail->invoice->is($this->invoice));

    expect($this->invoice->fresh()->status)->toBe('sent')
        ->and($this->invoice->fresh()->issued_at)->not->toBeNull();
});

it('does not email an invoice when the client has no address', function () {
    $this->client->forceFill(['email' => null])->save();

    $this->post(route('invoices.send', $this->invoice))->assertStatus(422);

    Mail::assertNothingSent();
    expect($this->invoice->fresh()->status)->toBe('draft');
});

it('does not email a paid or voided invoice', function () {
    $this->invoice->update(['status' => 'paid']);
    $this->post(route('invoices.send', $this->invoice))->assertStatus(422);

    $this->invoice->update(['status' => 'void']);
    $this->post(route('invoices.send', $this->invoice))->assertStatus(422);

    Mail::assertNothingSent();
});

it('does not email another workspace invoice', function () {
    $otherUser = User::factory()->create(['type' => 'freelancer']);
    $otherWorkspace = Workspace::create([
        'name' => 'Other WS',
        'slug' => 'other-ws-send',
        'owner_id' => $otherUser->id,
        'currency' => 'USD',
        'timezone' => 'UTC',
    ]);
    $otherClient = Client::create([
        'workspace_id' => $otherWorkspace->id,
        'name' => 'Other Client',
        'email' => 'other@client.example',
        'currency' => 'USD',
    ]);
    $otherInvoice = Invoice::create([
        'workspace_id' => $otherWorkspace->id,
        'client_id' => $otherClient->id,
        'created_by' => $otherUser->id,
        'number' => 'INV-2026-6600',
        'status' => 'draft',
        'currency' => 'USD',
        'subtotal' => 0,
        'tax_amount' => 0,
        'total' => 0,
        'tax_rate' => 0,
    ]);

    $this->post(route('invoices.send', $otherInvoice))->assertForbidden();

    Mail::assertNothingSent();
});

it('generates a payment link and stores it on the invoice', function () {
    $this->mock(StripeService::class)
        ->shouldReceive('createPaymentLink')
        ->once()
        ->andReturn('https://pay.stripe.com/test-link');

    $this->postJson(route('invoices.payment-link', $this->invoice))
        ->assertOk()
        ->assertJsonPath('url', 'https://pay.stripe.com/test-link');

    expect($this->invoice->fresh()->stripe_payment_link)->toBe('https://pay.stripe.com/test-link');
});

it('does not generate a payment link for a paid or voided invoice', function () {
    $this->mock(StripeService::class)->shouldNotReceive('createPaymentLink');

    $this->invoice->update(['status' => 'paid']);
    $this->postJson(route('invoices.payment-link', $this->invoice))->assertStatus(422);

    $this->invoice->update(['status' => 'void']);
    $this->postJson(route('invoices.payment-link', $this->invoice))->assertStatus(422);

    expect($this->invoice->fresh()->stripe_payment_link)->toBeNull();
});

it('does not generate a payment link for another workspace invoice', function () {
    $this->mock(StripeService::class)->shouldNotReceive('createPaymentLink');

    $otherUser = User::factory()->create(['type' => 'freelancer']);
    $otherWorkspace = Workspace::create([
        'name' => 'Other WS',
        'slug' => 'other-ws-link',
        'owner_id' => $otherUser->id,
        'currency' => 'USD',
        'timezone' => 'UTC',
    ]);
    $otherClient = Client::create([
        'workspace_id' => $otherWorkspace->id,
        'name' => 'Other Client',
        'currency' => 'USD',
    ]);
    $otherInvoice = Invoice::create([
        'workspace_id' => $otherWorkspace->id,
        'client_id' => $otherClient->id,
        'created_by' => $otherUser->id,
        'number' => 'INV-2026-6601',
        'status' => 'draft',
        'currency' => 'USD',
        'subtotal' => 0,
        'tax_amount' => 0,
        'total' => 0,
        'tax_rate' => 0,
    ]);

    $this->postJson(route('invoices.payment-link', $otherInvoice))->assertForbidden();
});
