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
        'name' => 'Acme',
        'currency' => 'USD',
    ]);

    $this->project = Project::create([
        'workspace_id' => $this->workspace->id,
        'client_id' => $this->client->id,
        'name' => 'Website',
        'status' => 'active',
        'type' => 'hourly',
        'hourly_rate' => 10000,
    ]);

    $this->actingAs($this->user);

    $this->billedInvoice = function (): Invoice {
        $entry = TimeEntry::create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'description' => 'Original work',
            'started_at' => now()->subHour(),
            'stopped_at' => now(),
            'duration_minutes' => 60,
            'hourly_rate' => 10000,
            'billable' => true,
        ]);

        test()->post(route('invoices.store'), [
            'client_id' => $this->client->id,
            'time_entry_ids' => [$entry->id],
            'tax_rate' => 21,
        ])->assertRedirect();

        return Invoice::latest('id')->first();
    };
});

it('copies an invoice into a new draft', function () {
    $source = ($this->billedInvoice)();
    $source->update(['notes' => 'Pay to IBAN', 'status' => 'sent']);

    $this->post(route('invoices.copy', $source))->assertRedirect();

    $copy = Invoice::latest('id')->first();

    expect($copy->id)->not->toBe($source->id)
        ->and($copy->status)->toBe('draft')
        ->and($copy->client_id)->toBe($source->client_id)
        ->and($copy->notes)->toBe('Pay to IBAN')
        ->and((float) $copy->tax_rate)->toBe((float) $source->tax_rate)
        ->and($copy->total)->toBe($source->total)
        ->and($copy->lines)->toHaveCount($source->lines->count());
});

it('gives the copy its own number and fresh dates', function () {
    $source = ($this->billedInvoice)();
    $source->update(['issued_at' => today()->subDays(60), 'due_at' => today()->subDays(30)]);

    $this->post(route('invoices.copy', $source))->assertRedirect();

    $copy = Invoice::latest('id')->first();

    expect($copy->number)->not->toBe($source->number)
        ->and($copy->issued_at->toDateString())->toBe(today()->toDateString())
        ->and($copy->due_at->toDateString())->toBe(today()->addDays(30)->toDateString());
});

it('does not carry the billable work over to the copy', function () {
    $source = ($this->billedInvoice)();

    expect($source->timeEntries()->count())->toBe(1);

    $this->post(route('invoices.copy', $source))->assertRedirect();

    $copy = Invoice::latest('id')->first();

    expect($copy->timeEntries()->count())->toBe(0)
        ->and($copy->projects()->count())->toBe(0)
        ->and($source->fresh()->timeEntries()->count())->toBe(1);
});

it('leaves the copied hours unavailable for rebilling', function () {
    $source = ($this->billedInvoice)();

    $this->post(route('invoices.copy', $source))->assertRedirect();

    // The source still owns the entry, so it must not reappear as unbilled.
    $this->getJson(route('invoices.unbilled-entries', ['client_id' => $this->client->id]))
        ->assertOk()
        ->assertJsonCount(0);
});

it('copies a fixed-price invoice without freeing the project', function () {
    $fixed = Project::create([
        'workspace_id' => $this->workspace->id,
        'client_id' => $this->client->id,
        'name' => 'Rebrand',
        'status' => 'active',
        'type' => 'fixed',
        'fixed_price' => 250000,
    ]);

    $this->post(route('invoices.store'), [
        'client_id' => $this->client->id,
        'project_ids' => [$fixed->id],
        'tax_rate' => 0,
    ])->assertRedirect();

    $source = Invoice::latest('id')->first();

    $this->post(route('invoices.copy', $source))->assertRedirect();

    $copy = Invoice::latest('id')->first();

    expect($copy->lines)->toHaveCount(1)
        ->and($copy->total)->toBe(250000)
        ->and($copy->projects()->count())->toBe(0);

    $this->getJson(route('invoices.unbilled-projects', ['client_id' => $this->client->id]))
        ->assertJsonCount(0);
});

it('honours the client payment terms on the copy', function () {
    $this->client->update(['payment_terms_days' => 7]);

    $source = ($this->billedInvoice)();

    $this->post(route('invoices.copy', $source))->assertRedirect();

    $copy = Invoice::latest('id')->first();

    expect($copy->due_at->toDateString())->toBe(today()->addDays(7)->toDateString());
});

it('cannot copy another workspace invoice', function () {
    $otherUser = User::factory()->create(['type' => 'freelancer']);
    $otherWorkspace = Workspace::create([
        'name' => 'Other WS',
        'slug' => 'other-ws-copy',
        'owner_id' => $otherUser->id,
        'currency' => 'USD',
        'timezone' => 'UTC',
    ]);
    $otherClient = Client::create([
        'workspace_id' => $otherWorkspace->id,
        'name' => 'Globex',
        'currency' => 'USD',
    ]);
    $otherInvoice = Invoice::create([
        'workspace_id' => $otherWorkspace->id,
        'client_id' => $otherClient->id,
        'created_by' => $otherUser->id,
        'number' => 'INV-2026-8800',
        'status' => 'draft',
        'currency' => 'USD',
        'subtotal' => 0,
        'tax_amount' => 0,
        'total' => 0,
        'tax_rate' => 0,
    ]);

    $this->post(route('invoices.copy', $otherInvoice))->assertForbidden();

    expect(Invoice::where('workspace_id', $this->workspace->id)->count())->toBe(0);
});
