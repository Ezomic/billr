<?php

declare(strict_types=1);

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\TimeEntry;
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

    $this->fixedProject = Project::create([
        'workspace_id' => $this->workspace->id,
        'client_id' => $this->client->id,
        'name' => 'Brand redesign',
        'status' => 'active',
        'type' => 'fixed',
        'fixed_price' => 250000,
    ]);

    $this->actingAs($this->user);
});

it('lists fixed-price projects that have not been billed', function () {
    $this->getJson(route('invoices.unbilled-projects', ['client_id' => $this->client->id]))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.id', $this->fixedProject->id)
        ->assertJsonPath('0.fixed_price', 250000);
});

it('creates an invoice from a fixed-price project', function () {
    $this->post(route('invoices.store'), [
        'client_id' => $this->client->id,
        'project_ids' => [$this->fixedProject->id],
        'tax_rate' => 0,
    ])->assertRedirect();

    $invoice = Invoice::with('lines')->first();

    expect($invoice)->not->toBeNull()
        ->and($invoice->lines)->toHaveCount(1)
        ->and($invoice->lines->first()->description)->toBe('Brand redesign')
        ->and($invoice->lines->first()->unit)->toBe('fixed')
        ->and($invoice->lines->first()->amount)->toBe(250000)
        ->and($invoice->total)->toBe(250000);
});

it('does not bill the same fixed-price project twice', function () {
    $payload = [
        'client_id' => $this->client->id,
        'project_ids' => [$this->fixedProject->id],
        'tax_rate' => 0,
    ];

    $this->post(route('invoices.store'), $payload)->assertRedirect();
    $this->post(route('invoices.store'), $payload)->assertSessionHasErrors('time_entry_ids');

    expect(Invoice::count())->toBe(1);

    $this->getJson(route('invoices.unbilled-projects', ['client_id' => $this->client->id]))
        ->assertJsonCount(0);
});

it('bills hours and a fixed fee on one invoice', function () {
    $hourly = Project::create([
        'workspace_id' => $this->workspace->id,
        'client_id' => $this->client->id,
        'name' => 'Retainer',
        'status' => 'active',
        'type' => 'hourly',
        'hourly_rate' => 10000,
    ]);

    $entry = TimeEntry::create([
        'project_id' => $hourly->id,
        'user_id' => $this->user->id,
        'started_at' => now()->subHour(),
        'stopped_at' => now(),
        'duration_minutes' => 60,
        'hourly_rate' => 10000,
        'billable' => true,
    ]);

    $this->post(route('invoices.store'), [
        'client_id' => $this->client->id,
        'time_entry_ids' => [$entry->id],
        'project_ids' => [$this->fixedProject->id],
        'tax_rate' => 0,
    ])->assertRedirect();

    $invoice = Invoice::with('lines')->first();

    expect($invoice->lines)->toHaveCount(2)
        ->and($invoice->total)->toBe(260000);
});

it('ignores a fixed-price project belonging to another client', function () {
    $otherClient = Client::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Other Client',
        'currency' => 'USD',
    ]);
    $otherProject = Project::create([
        'workspace_id' => $this->workspace->id,
        'client_id' => $otherClient->id,
        'name' => 'Not yours',
        'status' => 'active',
        'type' => 'fixed',
        'fixed_price' => 999900,
    ]);

    $this->post(route('invoices.store'), [
        'client_id' => $this->client->id,
        'project_ids' => [$otherProject->id],
        'tax_rate' => 0,
    ])->assertSessionHasErrors('time_entry_ids');

    expect(Invoice::count())->toBe(0)
        ->and($otherProject->fresh()->invoices)->toHaveCount(0);
});

it('adds a manual line to a draft invoice and recalculates the totals', function () {
    $this->post(route('invoices.store'), [
        'client_id' => $this->client->id,
        'project_ids' => [$this->fixedProject->id],
        'tax_rate' => 21,
    ])->assertRedirect();

    $invoice = Invoice::first();

    $this->post(route('invoices.lines.store', $invoice), [
        'description' => 'Stock photography',
        'quantity' => 3,
        'unit_price' => 5000,
    ])->assertRedirect();

    $invoice->refresh();

    expect($invoice->lines)->toHaveCount(2)
        ->and($invoice->subtotal)->toBe(265000)
        ->and($invoice->tax_amount)->toBe(55650)
        ->and($invoice->total)->toBe(320650);
});

it('removes a line from a draft invoice and recalculates the totals', function () {
    $this->post(route('invoices.store'), [
        'client_id' => $this->client->id,
        'project_ids' => [$this->fixedProject->id],
        'tax_rate' => 0,
    ])->assertRedirect();

    $invoice = Invoice::first();

    $this->post(route('invoices.lines.store', $invoice), [
        'description' => 'Extra',
        'quantity' => 1,
        'unit_price' => 10000,
    ])->assertRedirect();

    $extra = $invoice->fresh()->lines->firstWhere('description', 'Extra');

    $this->delete(route('invoices.lines.destroy', [$invoice, $extra]))->assertRedirect();

    $invoice->refresh();

    expect($invoice->lines)->toHaveCount(1)
        ->and($invoice->total)->toBe(250000);
});

it('cannot edit the lines of an invoice that is not a draft', function () {
    $this->post(route('invoices.store'), [
        'client_id' => $this->client->id,
        'project_ids' => [$this->fixedProject->id],
        'tax_rate' => 0,
    ])->assertRedirect();

    $invoice = Invoice::first();
    $line = $invoice->lines->first();

    $this->post(route('invoices.sent', $invoice))->assertRedirect();

    $this->post(route('invoices.lines.store', $invoice), [
        'description' => 'Sneaky',
        'quantity' => 1,
        'unit_price' => 100,
    ])->assertStatus(422);

    $this->delete(route('invoices.lines.destroy', [$invoice, $line]))->assertStatus(422);

    expect($invoice->fresh()->lines)->toHaveCount(1);
});

it('cannot touch the lines of another workspace invoice', function () {
    $otherUser = User::factory()->create(['type' => 'freelancer']);
    $otherWorkspace = Workspace::create([
        'name' => 'Other WS',
        'slug' => 'other-ws-lines',
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
        'number' => 'INV-2026-7700',
        'status' => 'draft',
        'currency' => 'USD',
        'subtotal' => 0,
        'tax_amount' => 0,
        'total' => 0,
        'tax_rate' => 0,
    ]);

    $this->post(route('invoices.lines.store', $otherInvoice), [
        'description' => 'Nope',
        'quantity' => 1,
        'unit_price' => 100,
    ])->assertForbidden();
});
