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

    $this->invoice = Invoice::create([
        'workspace_id' => $this->workspace->id,
        'client_id' => $this->client->id,
        'created_by' => $owner->id,
        'number' => 'INV-2026-0042',
        'status' => 'sent',
        'currency' => 'USD',
        'subtotal' => 10000,
        'tax_amount' => 0,
        'total' => 10000,
        'tax_rate' => 0,
    ]);

    $this->portalUser = User::factory()->create(['type' => 'client']);
    $this->client->portalUsers()->attach($this->portalUser->id);
});

it('lets a portal user download their own invoice as a pdf', function () {
    $response = $this->actingAs($this->portalUser)
        ->get(route('portal.invoices.pdf', $this->invoice));

    $response->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertDownload('INV-2026-0042.pdf');

    expect($response->getContent())->toStartWith('%PDF-');
});

it('does not let a portal user download an invoice for a client they cannot see', function () {
    $stranger = User::factory()->create(['type' => 'client']);

    $this->actingAs($stranger)
        ->get(route('portal.invoices.pdf', $this->invoice))
        ->assertForbidden();
});

it('does not let a guest download a portal invoice pdf', function () {
    $this->get(route('portal.invoices.pdf', $this->invoice))
        ->assertRedirect(route('login'));
});
