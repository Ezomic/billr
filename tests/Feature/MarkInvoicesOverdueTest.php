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

    $this->client = Client::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Test Client',
        'currency' => 'USD',
    ]);

    $this->makeInvoice = function (string $status, ?string $dueAt): Invoice {
        return Invoice::create([
            'workspace_id' => $this->workspace->id,
            'client_id' => $this->client->id,
            'created_by' => $this->user->id,
            'number' => 'INV-'.random_int(1000, 9999),
            'status' => $status,
            'currency' => 'USD',
            'subtotal' => 10000,
            'tax_amount' => 0,
            'total' => 10000,
            'tax_rate' => 0,
            'issued_at' => now()->subDays(40),
            'due_at' => $dueAt,
        ]);
    };
});

it('marks sent invoices past their due date as overdue', function () {
    $invoice = ($this->makeInvoice)('sent', now()->subDay()->toDateString());

    $this->artisan('invoices:mark-overdue')->assertSuccessful();

    expect($invoice->fresh()->status)->toBe('overdue');
});

it('does not touch sent invoices that are not yet due', function () {
    $invoice = ($this->makeInvoice)('sent', now()->addDay()->toDateString());

    $this->artisan('invoices:mark-overdue')->assertSuccessful();

    expect($invoice->fresh()->status)->toBe('sent');
});

it('does not touch paid invoices past their due date', function () {
    $invoice = ($this->makeInvoice)('paid', now()->subDay()->toDateString());

    $this->artisan('invoices:mark-overdue')->assertSuccessful();

    expect($invoice->fresh()->status)->toBe('paid');
});

it('does not touch draft invoices even with a past due date', function () {
    $invoice = ($this->makeInvoice)('draft', now()->subDay()->toDateString());

    $this->artisan('invoices:mark-overdue')->assertSuccessful();

    expect($invoice->fresh()->status)->toBe('draft');
});
