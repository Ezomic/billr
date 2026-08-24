<?php

declare(strict_types=1);

use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
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
        'name' => 'Acme',
        'currency' => 'USD',
    ]);

    $this->invoiceFor = function (string $status, string $number): Invoice {
        return Invoice::create([
            'workspace_id' => $this->workspace->id,
            'client_id' => $this->client->id,
            'created_by' => $this->user->id,
            'number' => $number,
            'status' => $status,
            'currency' => 'USD',
            'subtotal' => 10000,
            'tax_amount' => 0,
            'total' => 10000,
            'tax_rate' => 0,
            'issued_at' => today(),
            'due_at' => today()->addDays(30),
        ]);
    };

    $this->actingAs($this->user);
});

it('keeps a paid invoice readable after its client is deleted', function () {
    $invoice = ($this->invoiceFor)('paid', 'INV-2026-0001');

    $this->delete(route('clients.destroy', $this->client))->assertRedirect();

    expect(Client::find($this->client->id))->toBeNull()
        ->and(Client::withTrashed()->find($this->client->id))->not->toBeNull();

    $fresh = Invoice::with('client')->find($invoice->id);
    expect($fresh->client)->not->toBeNull()
        ->and($fresh->client->name)->toBe('Acme');
});

it('still renders the pdf of an invoice whose client is deleted', function () {
    $invoice = ($this->invoiceFor)('paid', 'INV-2026-0001');

    $this->delete(route('clients.destroy', $this->client))->assertRedirect();

    $response = $this->get(route('invoices.pdf', $invoice));

    $response->assertOk();
    expect($response->getContent())->toStartWith('%PDF-');
});

it('still shows the invoice page after its client is deleted', function () {
    $invoice = ($this->invoiceFor)('paid', 'INV-2026-0001');

    $this->delete(route('clients.destroy', $this->client))->assertRedirect();

    $this->get(route('invoices.show', $invoice))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('invoice.client.name', 'Acme'));
});

it('refuses to delete a client that is still owed money', function () {
    foreach (['draft' => 'INV-2026-0001', 'sent' => 'INV-2026-0002', 'overdue' => 'INV-2026-0003'] as $status => $number) {
        $client = Client::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Owing '.$status,
            'currency' => 'USD',
        ]);

        Invoice::create([
            'workspace_id' => $this->workspace->id,
            'client_id' => $client->id,
            'created_by' => $this->user->id,
            'number' => $number,
            'status' => $status,
            'currency' => 'USD',
            'subtotal' => 10000,
            'tax_amount' => 0,
            'total' => 10000,
            'tax_rate' => 0,
        ]);

        $this->delete(route('clients.destroy', $client))->assertStatus(422);

        expect(Client::find($client->id))->not->toBeNull();
    }
});

it('allows deleting a client whose invoices are all settled', function () {
    ($this->invoiceFor)('paid', 'INV-2026-0001');
    ($this->invoiceFor)('void', 'INV-2026-0002');

    $this->delete(route('clients.destroy', $this->client))->assertRedirect();

    expect(Client::find($this->client->id))->toBeNull();
});

it('allows deleting a client with no invoices at all', function () {
    $this->delete(route('clients.destroy', $this->client))->assertRedirect();

    expect(Client::find($this->client->id))->toBeNull();
});

it('keeps the portal invoice readable after its client is deleted', function () {
    $invoice = ($this->invoiceFor)('paid', 'INV-2026-0001');

    $portalUser = User::factory()->create(['type' => 'client']);
    $this->client->portalUsers()->attach($portalUser->id);

    $this->delete(route('clients.destroy', $this->client))->assertRedirect();

    $this->actingAs($portalUser)
        ->get(route('portal.invoices.show', $invoice))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('invoice.client.name', 'Acme'));
});
