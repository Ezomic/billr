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
            ->has('invoices.data', 1)
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

it('serves unbilled entries over http without being swallowed by the show route', function () {
    $entry = TimeEntry::create([
        'project_id' => $this->project->id,
        'user_id' => $this->user->id,
        'started_at' => now()->subHour(),
        'stopped_at' => now(),
        'duration_minutes' => 60,
        'hourly_rate' => 10000,
        'billable' => true,
    ]);

    $this->getJson(route('invoices.unbilled-entries', ['client_id' => $this->client->id]))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.id', $entry->id);
});

it('leaves already billed and non billable entries out of the unbilled list', function () {
    $billed = TimeEntry::create([
        'project_id' => $this->project->id,
        'user_id' => $this->user->id,
        'started_at' => now()->subHours(3),
        'stopped_at' => now()->subHours(2),
        'duration_minutes' => 60,
        'hourly_rate' => 10000,
        'billable' => true,
    ]);

    TimeEntry::create([
        'project_id' => $this->project->id,
        'user_id' => $this->user->id,
        'started_at' => now()->subHour(),
        'stopped_at' => now(),
        'duration_minutes' => 60,
        'hourly_rate' => 10000,
        'billable' => false,
    ]);

    $invoice = Invoice::create([
        'workspace_id' => $this->workspace->id,
        'client_id' => $this->client->id,
        'created_by' => $this->user->id,
        'number' => 'INV-2026-0500',
        'status' => 'draft',
        'currency' => 'USD',
        'subtotal' => 0,
        'tax_amount' => 0,
        'total' => 0,
        'tax_rate' => 0,
    ]);
    $invoice->timeEntries()->attach($billed->id);

    $this->getJson(route('invoices.unbilled-entries', ['client_id' => $this->client->id]))
        ->assertOk()
        ->assertJsonCount(0);
});

it('does not reuse a number after a draft invoice is deleted', function () {
    $makeInvoice = function () {
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

        return Invoice::latest('id')->first();
    };

    $first = $makeInvoice();
    $second = $makeInvoice();

    expect($first->number)->toBe('INV-'.now()->year.'-0001')
        ->and($second->number)->toBe('INV-'.now()->year.'-0002');

    $this->delete(route('invoices.destroy', $second))->assertRedirect();

    $third = $makeInvoice();

    expect($third->number)->toBe('INV-'.now()->year.'-0003');
});

it('allocates the same first number independently per workspace', function () {
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

    $otherUser = User::factory()->create(['type' => 'freelancer']);
    $otherWorkspace = Workspace::create([
        'name' => 'Second WS',
        'slug' => 'second-ws',
        'owner_id' => $otherUser->id,
        'currency' => 'USD',
        'timezone' => 'UTC',
    ]);
    $otherWorkspace->members()->attach($otherUser->id, ['role' => 'owner']);
    $otherUser->update(['current_workspace_id' => $otherWorkspace->id]);

    $otherClient = Client::create([
        'workspace_id' => $otherWorkspace->id,
        'name' => 'Second Client',
        'currency' => 'USD',
    ]);
    $otherProject = Project::create([
        'workspace_id' => $otherWorkspace->id,
        'client_id' => $otherClient->id,
        'name' => 'Second Project',
        'status' => 'active',
        'type' => 'hourly',
        'hourly_rate' => 10000,
    ]);
    $otherEntry = TimeEntry::create([
        'project_id' => $otherProject->id,
        'user_id' => $otherUser->id,
        'started_at' => now()->subHour(),
        'stopped_at' => now(),
        'duration_minutes' => 60,
        'hourly_rate' => 10000,
        'billable' => true,
    ]);

    $this->actingAs($otherUser)
        ->post(route('invoices.store'), [
            'client_id' => $otherClient->id,
            'time_entry_ids' => [$otherEntry->id],
            'tax_rate' => 0,
        ])->assertRedirect();

    $number = 'INV-'.now()->year.'-0001';

    expect(Invoice::where('workspace_id', $this->workspace->id)->value('number'))->toBe($number)
        ->and(Invoice::where('workspace_id', $otherWorkspace->id)->value('number'))->toBe($number);
});

it('refuses to bill time entries that are already on an invoice', function () {
    $entry = TimeEntry::create([
        'project_id' => $this->project->id,
        'user_id' => $this->user->id,
        'started_at' => now()->subHour(),
        'stopped_at' => now(),
        'duration_minutes' => 60,
        'hourly_rate' => 10000,
        'billable' => true,
    ]);

    $payload = [
        'client_id' => $this->client->id,
        'time_entry_ids' => [$entry->id],
        'tax_rate' => 0,
    ];

    $this->post(route('invoices.store'), $payload)->assertRedirect();

    $this->post(route('invoices.store'), $payload)
        ->assertSessionHasErrors('time_entry_ids');

    expect(Invoice::count())->toBe(1)
        ->and($entry->fresh()->invoices)->toHaveCount(1);
});

it('refuses to bill non billable or still running entries', function () {
    $nonBillable = TimeEntry::create([
        'project_id' => $this->project->id,
        'user_id' => $this->user->id,
        'started_at' => now()->subHours(2),
        'stopped_at' => now()->subHour(),
        'duration_minutes' => 60,
        'hourly_rate' => 10000,
        'billable' => false,
    ]);

    $running = TimeEntry::create([
        'project_id' => $this->project->id,
        'user_id' => $this->user->id,
        'started_at' => now()->subHour(),
        'stopped_at' => null,
        'hourly_rate' => 10000,
        'billable' => true,
    ]);

    $this->post(route('invoices.store'), [
        'client_id' => $this->client->id,
        'time_entry_ids' => [$nonBillable->id, $running->id],
        'tax_rate' => 0,
    ])->assertSessionHasErrors('time_entry_ids');

    expect(Invoice::count())->toBe(0);
});

it('bills only the invoiceable entries when the selection is mixed', function () {
    $billed = TimeEntry::create([
        'project_id' => $this->project->id,
        'user_id' => $this->user->id,
        'started_at' => now()->subHours(3),
        'stopped_at' => now()->subHours(2),
        'duration_minutes' => 60,
        'hourly_rate' => 10000,
        'billable' => true,
    ]);

    $fresh = TimeEntry::create([
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
        'time_entry_ids' => [$billed->id],
        'tax_rate' => 0,
    ])->assertRedirect();

    $this->post(route('invoices.store'), [
        'client_id' => $this->client->id,
        'time_entry_ids' => [$billed->id, $fresh->id],
        'tax_rate' => 0,
    ])->assertRedirect();

    $second = Invoice::latest('id')->first();

    expect($second->lines)->toHaveCount(1)
        ->and($second->total)->toBe(10000)
        ->and($billed->fresh()->invoices)->toHaveCount(1);
});

it('downloads the invoice as a pdf', function () {
    $invoice = Invoice::create([
        'workspace_id' => $this->workspace->id,
        'client_id' => $this->client->id,
        'created_by' => $this->user->id,
        'number' => 'INV-2026-0700',
        'status' => 'sent',
        'currency' => 'USD',
        'subtotal' => 10000,
        'tax_amount' => 0,
        'total' => 10000,
        'tax_rate' => 0,
    ]);

    $response = $this->get(route('invoices.pdf', $invoice));

    $response->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertDownload('INV-2026-0700.pdf');

    expect($response->getContent())->toStartWith('%PDF-');
});

it('cannot download a pdf for another workspace invoice', function () {
    $otherUser = User::factory()->create(['type' => 'freelancer']);
    $otherWorkspace = Workspace::create([
        'name' => 'Other WS',
        'slug' => 'other-ws-pdf',
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
        'number' => 'INV-2026-9800',
        'status' => 'draft',
        'currency' => 'USD',
        'subtotal' => 0,
        'tax_amount' => 0,
        'total' => 0,
        'tax_rate' => 0,
    ]);

    $this->get(route('invoices.pdf', $otherInvoice))->assertForbidden();
});

it('voids a sent invoice and releases its time entries', function () {
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

    $invoice = Invoice::latest('id')->first();
    $this->post(route('invoices.sent', $invoice))->assertRedirect();

    $this->post(route('invoices.void', $invoice))->assertRedirect();

    expect($invoice->fresh()->status)->toBe('void')
        ->and($entry->fresh()->invoices)->toHaveCount(0);

    $this->getJson(route('invoices.unbilled-entries', ['client_id' => $this->client->id]))
        ->assertJsonCount(1)
        ->assertJsonPath('0.id', $entry->id);
});

it('can rebill the time entries released by a voided invoice', function () {
    $entry = TimeEntry::create([
        'project_id' => $this->project->id,
        'user_id' => $this->user->id,
        'started_at' => now()->subHour(),
        'stopped_at' => now(),
        'duration_minutes' => 60,
        'hourly_rate' => 10000,
        'billable' => true,
    ]);

    $payload = [
        'client_id' => $this->client->id,
        'time_entry_ids' => [$entry->id],
        'tax_rate' => 0,
    ];

    $this->post(route('invoices.store'), $payload)->assertRedirect();
    $first = Invoice::latest('id')->first();

    $this->post(route('invoices.void', $first))->assertRedirect();

    $this->post(route('invoices.store'), $payload)->assertRedirect();
    $second = Invoice::latest('id')->first();

    expect($second->id)->not->toBe($first->id)
        ->and($second->total)->toBe(10000)
        ->and($second->number)->not->toBe($first->number);
});

it('cannot void a paid invoice', function () {
    $invoice = Invoice::create([
        'workspace_id' => $this->workspace->id,
        'client_id' => $this->client->id,
        'created_by' => $this->user->id,
        'number' => 'INV-2026-0800',
        'status' => 'paid',
        'currency' => 'USD',
        'subtotal' => 5000,
        'tax_amount' => 0,
        'total' => 5000,
        'tax_rate' => 0,
    ]);

    $this->post(route('invoices.void', $invoice))->assertStatus(422);

    expect($invoice->fresh()->status)->toBe('paid');
});

it('refuses to send or settle an invoice that is void', function () {
    $invoice = Invoice::create([
        'workspace_id' => $this->workspace->id,
        'client_id' => $this->client->id,
        'created_by' => $this->user->id,
        'number' => 'INV-2026-0801',
        'status' => 'void',
        'currency' => 'USD',
        'subtotal' => 5000,
        'tax_amount' => 0,
        'total' => 5000,
        'tax_rate' => 0,
    ]);

    $this->post(route('invoices.sent', $invoice))->assertStatus(422);
    $this->post(route('invoices.paid', $invoice))->assertStatus(422);
    $this->post(route('invoices.void', $invoice))->assertStatus(422);

    expect($invoice->fresh()->status)->toBe('void');
});

it('leaves a voided invoice out of the overdue sweep', function () {
    $invoice = Invoice::create([
        'workspace_id' => $this->workspace->id,
        'client_id' => $this->client->id,
        'created_by' => $this->user->id,
        'number' => 'INV-2026-0802',
        'status' => 'void',
        'currency' => 'USD',
        'subtotal' => 5000,
        'tax_amount' => 0,
        'total' => 5000,
        'tax_rate' => 0,
        'due_at' => today()->subDays(10),
    ]);

    $this->artisan('invoices:mark-overdue')->assertSuccessful();

    expect($invoice->fresh()->status)->toBe('void');
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
