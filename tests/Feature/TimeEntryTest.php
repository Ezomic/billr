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
});

it('can list time entries', function () {
    TimeEntry::create([
        'project_id' => $this->project->id,
        'user_id' => $this->user->id,
        'started_at' => now()->subHour(),
        'stopped_at' => now(),
        'duration_minutes' => 60,
        'billable' => true,
    ]);

    $this->get(route('time.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('time/Index')
            ->has('entries.data', 1)
            ->has('projects', 1)
        );
});

it('stores a manual entry and computes its duration', function () {
    $this->post(route('time.store'), [
        'project_id' => $this->project->id,
        'description' => 'Manual work',
        'started_at' => now()->subMinutes(90)->toDateTimeString(),
        'stopped_at' => now()->toDateTimeString(),
        'billable' => true,
    ])->assertRedirect();

    $entry = TimeEntry::first();

    expect($entry->duration_minutes)->toBe(90)
        ->and($entry->user_id)->toBe($this->user->id)
        ->and($entry->hourly_rate)->toBe(10000);
});

it('rejects an entry that stops before it starts', function () {
    $this->post(route('time.store'), [
        'project_id' => $this->project->id,
        'started_at' => now()->toDateTimeString(),
        'stopped_at' => now()->subHour()->toDateTimeString(),
    ])->assertSessionHasErrors('stopped_at');

    expect(TimeEntry::count())->toBe(0);
});

it('cannot log time against a project in another workspace', function () {
    $otherUser = User::factory()->create(['type' => 'freelancer']);
    $otherWorkspace = Workspace::create([
        'name' => 'Other WS',
        'slug' => 'other-ws-time',
        'owner_id' => $otherUser->id,
        'currency' => 'USD',
        'timezone' => 'UTC',
    ]);
    $otherClient = Client::create([
        'workspace_id' => $otherWorkspace->id,
        'name' => 'Other Client',
        'currency' => 'USD',
    ]);
    $otherProject = Project::create([
        'workspace_id' => $otherWorkspace->id,
        'client_id' => $otherClient->id,
        'name' => 'Theirs',
        'status' => 'active',
        'type' => 'hourly',
        'hourly_rate' => 9500,
    ]);

    $this->post(route('time.store'), [
        'project_id' => $otherProject->id,
        'started_at' => now()->subHour()->toDateTimeString(),
        'stopped_at' => now()->toDateTimeString(),
    ])->assertNotFound();

    expect(TimeEntry::count())->toBe(0);
});

it('starts a running timer', function () {
    $this->post(route('time.start', $this->project->id))->assertRedirect();

    $entry = TimeEntry::first();

    expect($entry)->not->toBeNull()
        ->and($entry->stopped_at)->toBeNull()
        ->and($entry->isRunning())->toBeTrue()
        ->and($entry->hourly_rate)->toBe(10000);
});

it('stops a running timer and records the elapsed minutes', function () {
    $entry = TimeEntry::create([
        'project_id' => $this->project->id,
        'user_id' => $this->user->id,
        'started_at' => now()->subMinutes(45),
        'billable' => true,
    ]);

    $this->post(route('time.stop', $entry))->assertRedirect();

    expect($entry->fresh()->stopped_at)->not->toBeNull()
        ->and($entry->fresh()->duration_minutes)->toBe(45);
});

it('stops the running timer instead of discarding it when another is started', function () {
    $other = Project::create([
        'workspace_id' => $this->workspace->id,
        'client_id' => $this->client->id,
        'name' => 'Second Project',
        'status' => 'active',
        'type' => 'hourly',
        'hourly_rate' => 12000,
    ]);

    $running = TimeEntry::create([
        'project_id' => $this->project->id,
        'user_id' => $this->user->id,
        'started_at' => now()->subMinutes(25),
        'billable' => true,
        'hourly_rate' => 10000,
    ]);

    $this->post(route('time.start', $other->id))->assertRedirect();

    expect($running->fresh())->not->toBeNull()
        ->and($running->fresh()->stopped_at)->not->toBeNull()
        ->and($running->fresh()->duration_minutes)->toBe(25);

    expect(TimeEntry::count())->toBe(2);

    $new = TimeEntry::whereNull('stopped_at')->first();

    expect($new->project_id)->toBe($other->id)
        ->and($new->hourly_rate)->toBe(12000);
});

it('does not stop another user timer when starting one', function () {
    $otherUser = User::factory()->create(['type' => 'freelancer']);

    $theirs = TimeEntry::create([
        'project_id' => $this->project->id,
        'user_id' => $otherUser->id,
        'started_at' => now()->subMinutes(10),
        'billable' => true,
    ]);

    $this->post(route('time.start', $this->project->id))->assertRedirect();

    expect($theirs->fresh()->stopped_at)->toBeNull();
});

it('cannot start a timer on a project in another workspace', function () {
    $otherUser = User::factory()->create(['type' => 'freelancer']);
    $otherWorkspace = Workspace::create([
        'name' => 'Other WS',
        'slug' => 'other-ws-timer',
        'owner_id' => $otherUser->id,
        'currency' => 'USD',
        'timezone' => 'UTC',
    ]);
    $otherClient = Client::create([
        'workspace_id' => $otherWorkspace->id,
        'name' => 'Other Client',
        'currency' => 'USD',
    ]);
    $otherProject = Project::create([
        'workspace_id' => $otherWorkspace->id,
        'client_id' => $otherClient->id,
        'name' => 'Theirs',
        'status' => 'active',
        'type' => 'hourly',
        'hourly_rate' => 9500,
    ]);

    $this->post(route('time.start', $otherProject->id))->assertNotFound();

    expect(TimeEntry::count())->toBe(0);
});

it('can update and delete an own entry', function () {
    $entry = TimeEntry::create([
        'project_id' => $this->project->id,
        'user_id' => $this->user->id,
        'started_at' => now()->subHour(),
        'stopped_at' => now(),
        'duration_minutes' => 60,
        'billable' => true,
    ]);

    $this->put(route('time.update', $entry), [
        'project_id' => $this->project->id,
        'description' => 'Corrected',
        'started_at' => now()->subMinutes(30)->toDateTimeString(),
        'stopped_at' => now()->toDateTimeString(),
        'billable' => false,
    ])->assertRedirect();

    expect($entry->fresh()->description)->toBe('Corrected')
        ->and($entry->fresh()->duration_minutes)->toBe(30)
        ->and($entry->fresh()->billable)->toBeFalse();

    $this->delete(route('time.destroy', $entry))->assertRedirect();

    expect(TimeEntry::count())->toBe(0);
});

it('refuses to edit or delete an entry that has been invoiced', function () {
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

    $this->put(route('time.update', $entry), [
        'project_id' => $this->project->id,
        'description' => 'Padded',
        'started_at' => now()->subHours(9)->toDateTimeString(),
        'stopped_at' => now()->toDateTimeString(),
    ])->assertStatus(422);

    $this->delete(route('time.destroy', $entry))->assertStatus(422);

    expect($entry->fresh()->duration_minutes)->toBe(60)
        ->and($entry->fresh()->description)->toBeNull()
        ->and(TimeEntry::count())->toBe(1);
});

it('makes an entry editable again once its invoice is voided', function () {
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
    $this->post(route('invoices.void', $invoice))->assertRedirect();

    $this->put(route('time.update', $entry), [
        'project_id' => $this->project->id,
        'description' => 'Corrected after void',
        'started_at' => now()->subMinutes(30)->toDateTimeString(),
        'stopped_at' => now()->toDateTimeString(),
    ])->assertRedirect();

    expect($entry->fresh()->description)->toBe('Corrected after void')
        ->and($entry->fresh()->duration_minutes)->toBe(30);
});

it('cannot stop, update or delete another user entry', function () {
    $otherUser = User::factory()->create(['type' => 'freelancer']);

    $entry = TimeEntry::create([
        'project_id' => $this->project->id,
        'user_id' => $otherUser->id,
        'started_at' => now()->subHour(),
        'billable' => true,
    ]);

    $this->post(route('time.stop', $entry))->assertForbidden();

    $this->put(route('time.update', $entry), [
        'project_id' => $this->project->id,
        'started_at' => now()->subHour()->toDateTimeString(),
        'stopped_at' => now()->toDateTimeString(),
    ])->assertForbidden();

    $this->delete(route('time.destroy', $entry))->assertForbidden();

    expect($entry->fresh())->not->toBeNull()
        ->and($entry->fresh()->stopped_at)->toBeNull();
});
