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

    $this->project = Project::create([
        'workspace_id' => $this->workspace->id,
        'client_id' => $this->client->id,
        'name' => 'Test Project',
        'status' => 'active',
        'type' => 'hourly',
        'hourly_rate' => 10000,
    ]);

    $this->actingAs($this->user);

    $this->invoiceNow = function (): Invoice {
        $entry = TimeEntry::create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'started_at' => now()->subHour(),
            'stopped_at' => now(),
            'duration_minutes' => 60,
            'hourly_rate' => 10000,
            'billable' => true,
        ]);

        test()->post(route('invoices.store'), [
            'client_id' => $this->client->id,
            'time_entry_ids' => [$entry->id],
            'tax_rate' => 0,
        ])->assertRedirect();

        return Invoice::latest('id')->first();
    };
});

it('defaults a new workspace to 30 day terms', function () {
    expect($this->workspace->fresh()->payment_terms_days)->toBe(30);

    $invoice = ($this->invoiceNow)();

    expect($invoice->due_at->toDateString())->toBe(today()->addDays(30)->toDateString());
});

it('uses the workspace terms when the client has no override', function () {
    $this->workspace->update(['payment_terms_days' => 14]);

    $invoice = ($this->invoiceNow)();

    expect($invoice->due_at->toDateString())->toBe(today()->addDays(14)->toDateString());
});

it('prefers the client terms over the workspace terms', function () {
    $this->workspace->update(['payment_terms_days' => 14]);
    $this->client->update(['payment_terms_days' => 7]);

    $invoice = ($this->invoiceNow)();

    expect($invoice->due_at->toDateString())->toBe(today()->addDays(7)->toDateString());
});

it('supports due on receipt', function () {
    $this->client->update(['payment_terms_days' => 0]);

    $invoice = ($this->invoiceNow)();

    expect($invoice->due_at->toDateString())->toBe(today()->toDateString());
});

it('saves the workspace default from settings', function () {
    $this->put(route('settings.workspace.update'), [
        'name' => $this->workspace->name,
        'currency' => 'USD',
        'timezone' => 'UTC',
        'payment_terms_days' => 45,
    ])->assertRedirect();

    expect($this->workspace->fresh()->payment_terms_days)->toBe(45);
});

it('rejects nonsense workspace terms', function () {
    $this->put(route('settings.workspace.update'), [
        'name' => $this->workspace->name,
        'currency' => 'USD',
        'timezone' => 'UTC',
        'payment_terms_days' => 900,
    ])->assertSessionHasErrors('payment_terms_days');

    expect($this->workspace->fresh()->payment_terms_days)->toBe(30);
});

it('saves and clears the client override', function () {
    $this->put(route('clients.update', $this->client), [
        'name' => 'Test Client',
        'payment_terms_days' => 7,
    ])->assertRedirect();

    expect($this->client->fresh()->payment_terms_days)->toBe(7);

    $this->put(route('clients.update', $this->client), [
        'name' => 'Test Client',
        'payment_terms_days' => null,
    ])->assertRedirect();

    expect($this->client->fresh()->payment_terms_days)->toBeNull();
});
