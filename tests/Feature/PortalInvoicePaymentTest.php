<?php

declare(strict_types=1);

use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    $owner = User::factory()->create(['type' => 'freelancer']);

    $this->workspace = Workspace::create([
        'name' => 'Test Workspace',
        'slug' => 'test-workspace',
        'owner_id' => $owner->id,
        'currency' => 'USD',
        'timezone' => 'UTC',
    ]);

    $this->client = Client::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Test Client',
        'currency' => 'USD',
    ]);

    $this->makeInvoice = function (string $status): Invoice {
        static $n = 0;
        $n++;

        return Invoice::create([
            'workspace_id' => $this->workspace->id,
            'client_id' => $this->client->id,
            'created_by' => $this->workspace->owner_id,
            'number' => 'INV-2026-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT),
            'status' => $status,
            'currency' => 'USD',
            'subtotal' => 10000,
            'tax_amount' => 0,
            'total' => 10000,
            'tax_rate' => 0,
            'stripe_payment_link' => 'https://pay.stripe.com/test-link',
            'stripe_session_id' => 'cs_secret_internal',
        ]);
    };

    $this->portalUser = User::factory()->create(['type' => 'client']);
    $this->client->portalUsers()->attach($this->portalUser->id);
});

it('gives the client a payment url on an unpaid invoice', function () {
    $invoice = ($this->makeInvoice)('sent');

    $this->actingAs($this->portalUser)
        ->get(route('portal.invoices.show', $invoice))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('portal/Invoice')
            ->where('invoice.payment_url', 'https://pay.stripe.com/test-link')
        );
});

it('withholds the payment url once the invoice is paid or void', function () {
    foreach (['paid', 'void'] as $status) {
        $invoice = ($this->makeInvoice)($status);

        $this->actingAs($this->portalUser)
            ->get(route('portal.invoices.show', $invoice))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('invoice.payment_url', null));
    }
});

it('gives no payment url when no link has been generated', function () {
    $invoice = ($this->makeInvoice)('sent');
    $invoice->update(['stripe_payment_link' => null]);

    $this->actingAs($this->portalUser)
        ->get(route('portal.invoices.show', $invoice))
        ->assertInertia(fn ($page) => $page->where('invoice.payment_url', null));
});

it('does not leak internal stripe columns to the portal', function () {
    $invoice = ($this->makeInvoice)('sent');

    $this->actingAs($this->portalUser)
        ->get(route('portal.invoices.show', $invoice))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->missing('invoice.stripe_session_id')
            ->missing('invoice.stripe_payment_link')
            ->missing('invoice.workspace_id')
            ->missing('invoice.created_by')
        );
});

it('still renders the invoice the client needs to read', function () {
    $invoice = ($this->makeInvoice)('sent');

    $this->actingAs($this->portalUser)
        ->get(route('portal.invoices.show', $invoice))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('invoice.number', $invoice->number)
            ->where('invoice.total', 10000)
            ->where('invoice.client.name', 'Test Client')
            ->where('invoice.workspace.name', 'Test Workspace')
        );
});
