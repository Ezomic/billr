<?php

declare(strict_types=1);

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceLine;
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

    $this->project = Project::create([
        'workspace_id' => $this->workspace->id,
        'client_id' => $this->client->id,
        'name' => 'Test Project',
        'status' => 'active',
        'type' => 'hourly',
        'hourly_rate' => 10000, // $100/hr in cents
    ]);

    $this->actingAs($this->user);
});

it('can list invoices', function () {
    Invoice::create([
        'workspace_id' => $this->workspace->id,
        'client_id' => $this->client->id,
        'created_by' => $this->user->id,
        'number' => 'INV-2026-0001',
        'status' => 'draft',
        'currency' => 'USD',
        'subtotal' => 0,
        'tax_amount' => 0,
        'total' => 0,
        'tax_rate' => 0,
    ]);

    $this->get(route('invoices.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('invoices/Index')
            ->has('invoices', 1)
        );
});

it('can create an invoice from time entries', function () {
    $entry = TimeEntry::create([
        'project_id' => $this->project->id,
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
        'tax_rate' => 0,
    ])->assertRedirect();

    $invoice = Invoice::first();
    expect($invoice)->not->toBeNull()
        ->and($invoice->client_id)->toBe($this->client->id)
        ->and($invoice->subtotal)->toBe(10000)
        ->and($invoice->total)->toBe(10000);

    expect(InvoiceLine::count())->toBe(1);
});

it('invoice has correct line items', function () {
    $entry = TimeEntry::create([
        'project_id' => $this->project->id,
        'user_id' => $this->user->id,
        'description' => 'Design work',
        'started_at' => now()->subHours(2),
        'stopped_at' => now(),
        'duration_minutes' => 120,
        'hourly_rate' => 10000,
        'billable' => true,
    ]);

    $this->post(route('invoices.store'), [
        'client_id' => $this->client->id,
        'time_entry_ids' => [$entry->id],
        'tax_rate' => 21,
    ]);

    $invoice = Invoice::with('lines')->first();

    expect($invoice->lines)->toHaveCount(1)
        ->and($invoice->lines->first()->description)->toBe('Design work')
        ->and($invoice->lines->first()->quantity)->toBe(120)
        ->and($invoice->tax_amount)->toBeGreaterThan(0);
});

it('can mark an invoice as paid', function () {
    $invoice = Invoice::create([
        'workspace_id' => $this->workspace->id,
        'client_id' => $this->client->id,
        'created_by' => $this->user->id,
        'number' => 'INV-2026-0001',
        'status' => 'sent',
        'currency' => 'USD',
        'subtotal' => 5000,
        'tax_amount' => 0,
        'total' => 5000,
        'tax_rate' => 0,
    ]);

    $this->post(route('invoices.paid', $invoice))
        ->assertRedirect();

    expect($invoice->fresh()->status)->toBe('paid')
        ->and($invoice->fresh()->paid_at)->not->toBeNull();
});

it('can mark an invoice as sent', function () {
    $invoice = Invoice::create([
        'workspace_id' => $this->workspace->id,
        'client_id' => $this->client->id,
        'created_by' => $this->user->id,
        'number' => 'INV-2026-0002',
        'status' => 'draft',
        'currency' => 'USD',
        'subtotal' => 5000,
        'tax_amount' => 0,
        'total' => 5000,
        'tax_rate' => 0,
    ]);

    $this->post(route('invoices.sent', $invoice))
        ->assertRedirect();

    expect($invoice->fresh()->status)->toBe('sent');
});

it('cannot delete a sent invoice', function () {
    $invoice = Invoice::create([
        'workspace_id' => $this->workspace->id,
        'client_id' => $this->client->id,
        'created_by' => $this->user->id,
        'number' => 'INV-2026-0003',
        'status' => 'sent',
        'currency' => 'USD',
        'subtotal' => 0,
        'tax_amount' => 0,
        'total' => 0,
        'tax_rate' => 0,
    ]);

    $this->delete(route('invoices.destroy', $invoice))
        ->assertStatus(422);
});

it('cannot access another workspace invoice', function () {
    $otherUser = User::factory()->create(['type' => 'freelancer']);
    $otherWorkspace = Workspace::create([
        'name' => 'Other WS',
        'slug' => 'other-ws',
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
        'number' => 'INV-2026-9999',
        'status' => 'draft',
        'currency' => 'USD',
        'subtotal' => 0,
        'tax_amount' => 0,
        'total' => 0,
        'tax_rate' => 0,
    ]);

    $this->get(route('invoices.show', $otherInvoice))->assertForbidden();
});
