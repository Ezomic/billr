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
        'email' => 'client@example.com',
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

    $this->entry = TimeEntry::create([
        'project_id' => $this->project->id,
        'user_id' => $this->user->id,
        'started_at' => now()->subHour(),
        'stopped_at' => now(),
        'duration_minutes' => 60,
        'billable' => true,
        'client_approved' => false,
    ]);
});

it('sends a portal access link and sets a token on the client', function () {
    $this->actingAs($this->user);

    $this->post(route('clients.portal-access', $this->client))
        ->assertRedirect();

    expect($this->client->fresh()->portal_token)->not->toBeNull();
});

it('shows the unbilled entries on the token-based portal page', function () {
    $this->client->forceFill(['portal_token' => 'test-token'])->save();

    $this->get(route('client-portal.show', 'test-token'))
        ->assertOk()
        ->assertSee($this->project->name);
});

it('approves the selected entries via the token-based portal without authentication', function () {
    $this->client->forceFill(['portal_token' => 'test-token'])->save();

    $this->post(route('client-portal.approve', 'test-token'), [
        'time_entry_ids' => [$this->entry->id],
    ])->assertRedirect(route('client-portal.show', 'test-token'));

    expect($this->entry->fresh()->client_approved)->toBeTrue();
});

it('approves only the entries the client selected', function () {
    $this->client->forceFill(['portal_token' => 'test-token'])->save();

    $unselected = TimeEntry::create([
        'project_id' => $this->project->id,
        'user_id' => $this->user->id,
        'started_at' => now()->subHours(3),
        'stopped_at' => now()->subHours(2),
        'duration_minutes' => 60,
        'billable' => true,
        'client_approved' => false,
    ]);

    $this->post(route('client-portal.approve', 'test-token'), [
        'time_entry_ids' => [$this->entry->id],
    ])->assertRedirect();

    expect($this->entry->fresh()->client_approved)->toBeTrue()
        ->and($unselected->fresh()->client_approved)->toBeFalse();
});

it('rejects an approval that names no entries', function () {
    $this->client->forceFill(['portal_token' => 'test-token'])->save();

    $this->post(route('client-portal.approve', 'test-token'), [
        'time_entry_ids' => [],
    ])->assertSessionHasErrors('time_entry_ids');

    expect($this->entry->fresh()->client_approved)->toBeFalse();
});

it('does not approve another client entries via a different token', function () {
    $otherClient = Client::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Other Client',
        'email' => 'other@example.com',
        'currency' => 'USD',
    ]);
    $otherClient->forceFill(['portal_token' => 'other-token'])->save();
    $this->client->forceFill(['portal_token' => 'test-token'])->save();

    $this->post(route('client-portal.approve', 'other-token'), [
        'time_entry_ids' => [$this->entry->id],
    ]);

    expect($this->entry->fresh()->client_approved)->toBeFalse();
});

it('does not approve an entry that was invoiced after the page was rendered', function () {
    $this->client->forceFill(['portal_token' => 'test-token'])->save();

    $invoice = Invoice::create([
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
    $invoice->timeEntries()->attach($this->entry->id);

    $this->post(route('client-portal.approve', 'test-token'), [
        'time_entry_ids' => [$this->entry->id],
    ])->assertSessionHasErrors('time_entry_ids');

    expect($this->entry->fresh()->client_approved)->toBeFalse();
});

it('throttles repeated hits on the token routes', function () {
    $this->client->forceFill(['portal_token' => 'test-token'])->save();

    foreach (range(1, 20) as $ignored) {
        $this->get(route('client-portal.show', 'test-token'));
    }

    $this->get(route('client-portal.show', 'test-token'))->assertStatus(429);
});
