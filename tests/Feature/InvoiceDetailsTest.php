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
        'name' => 'Test Client',
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
        'issued_at' => today(),
        'due_at' => today()->addDays(30),
    ]);

    $this->actingAs($this->user);
});

it('edits the notes and dates of a draft', function () {
    $this->put(route('invoices.update', $this->invoice), [
        'notes' => 'Pay to IBAN NL00 BANK 0123 4567 89',
        'issued_at' => today()->subDay()->toDateString(),
        'due_at' => today()->addDays(7)->toDateString(),
    ])->assertRedirect();

    $invoice = $this->invoice->fresh();

    expect($invoice->notes)->toBe('Pay to IBAN NL00 BANK 0123 4567 89')
        ->and($invoice->issued_at->toDateString())->toBe(today()->subDay()->toDateString())
        ->and($invoice->due_at->toDateString())->toBe(today()->addDays(7)->toDateString());
});

it('clears the notes when submitted empty', function () {
    $this->invoice->update(['notes' => 'Something']);

    $this->put(route('invoices.update', $this->invoice), [
        'notes' => '   ',
        'issued_at' => today()->toDateString(),
        'due_at' => today()->addDays(30)->toDateString(),
    ])->assertRedirect();

    expect($this->invoice->fresh()->notes)->toBeNull();
});

it('refuses a due date before the issue date', function () {
    $this->put(route('invoices.update', $this->invoice), [
        'notes' => null,
        'issued_at' => today()->toDateString(),
        'due_at' => today()->subDays(5)->toDateString(),
    ])->assertSessionHasErrors('due_at');

    expect($this->invoice->fresh()->due_at->toDateString())
        ->toBe(today()->addDays(30)->toDateString());
});

it('refuses to edit an invoice that is not a draft', function () {
    foreach (['sent', 'paid', 'overdue', 'void'] as $status) {
        $this->invoice->update(['status' => $status]);

        $this->put(route('invoices.update', $this->invoice), [
            'notes' => 'Sneaky',
            'issued_at' => today()->toDateString(),
            'due_at' => today()->addDays(1)->toDateString(),
        ])->assertStatus(422);
    }

    expect($this->invoice->fresh()->notes)->toBeNull();
});

it('cannot edit another workspace invoice', function () {
    $otherUser = User::factory()->create(['type' => 'freelancer']);
    $otherWorkspace = Workspace::create([
        'name' => 'Other WS',
        'slug' => 'other-ws-details',
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
        'number' => 'INV-2026-5500',
        'status' => 'draft',
        'currency' => 'USD',
        'subtotal' => 0,
        'tax_amount' => 0,
        'total' => 0,
        'tax_rate' => 0,
    ]);

    $this->put(route('invoices.update', $otherInvoice), [
        'notes' => 'Nope',
        'issued_at' => today()->toDateString(),
        'due_at' => today()->addDays(1)->toDateString(),
    ])->assertForbidden();
});

it('shows the saved notes on the pdf', function () {
    $this->put(route('invoices.update', $this->invoice), [
        'notes' => 'Thanks for your business',
        'issued_at' => today()->toDateString(),
        'due_at' => today()->addDays(30)->toDateString(),
    ])->assertRedirect();

    $response = $this->get(route('invoices.pdf', $this->invoice));

    $response->assertOk();
    expect($response->getContent())->toStartWith('%PDF-');
});
