<?php

declare(strict_types=1);

use App\Models\Client;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    $this->owner = User::factory()->create(['type' => 'freelancer', 'name' => 'Owner']);
    $this->member = User::factory()->create(['type' => 'freelancer', 'name' => 'Member']);

    $this->workspace = Workspace::create([
        'name' => 'Test Workspace',
        'slug' => 'test-workspace',
        'owner_id' => $this->owner->id,
        'currency' => 'USD',
        'timezone' => 'UTC',
    ]);
    $this->workspace->members()->attach($this->owner->id, ['role' => 'owner']);
    $this->workspace->members()->attach($this->member->id, ['role' => 'member']);

    $this->owner->update(['current_workspace_id' => $this->workspace->id]);
    $this->member->update(['current_workspace_id' => $this->workspace->id]);

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

    $this->logTime = function (User $user, string $description): TimeEntry {
        return TimeEntry::create([
            'project_id' => $this->project->id,
            'user_id' => $user->id,
            'description' => $description,
            'started_at' => now()->subHour(),
            'stopped_at' => now(),
            'duration_minutes' => 60,
            'hourly_rate' => 10000,
            'billable' => true,
        ]);
    };
});

it('shows an owner only their own entries by default', function () {
    ($this->logTime)($this->owner, 'Owner work');
    ($this->logTime)($this->member, 'Member work');

    $this->actingAs($this->owner)
        ->get(route('time.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('isOwner', true)
            ->has('entries.data', 1)
            ->where('entries.data.0.description', 'Owner work')
        );
});

it('lets an owner see everyone', function () {
    ($this->logTime)($this->owner, 'Owner work');
    ($this->logTime)($this->member, 'Member work');

    $this->actingAs($this->owner)
        ->get(route('time.index', ['user_id' => 'all']))
        ->assertInertia(fn ($page) => $page->has('entries.data', 2));
});

it('lets an owner filter to one member', function () {
    ($this->logTime)($this->owner, 'Owner work');
    ($this->logTime)($this->member, 'Member work');

    $this->actingAs($this->owner)
        ->get(route('time.index', ['user_id' => $this->member->id]))
        ->assertInertia(fn ($page) => $page
            ->has('entries.data', 1)
            ->where('entries.data.0.description', 'Member work')
        );
});

it('gives an owner the member list', function () {
    $this->actingAs($this->owner)
        ->get(route('time.index'))
        ->assertInertia(fn ($page) => $page->has('members', 2));
});

it('keeps a member scoped to their own entries whatever they ask for', function () {
    ($this->logTime)($this->owner, 'Owner work');
    ($this->logTime)($this->member, 'Member work');

    foreach (['all', (string) $this->owner->id] as $attempt) {
        $this->actingAs($this->member)
            ->get(route('time.index', ['user_id' => $attempt]))
            ->assertInertia(fn ($page) => $page
                ->where('isOwner', false)
                ->has('members', 0)
                ->has('entries.data', 1)
                ->where('entries.data.0.description', 'Member work')
            );
    }
});

it('ignores a user outside the workspace', function () {
    $stranger = User::factory()->create(['type' => 'freelancer']);
    ($this->logTime)($this->owner, 'Owner work');

    $this->actingAs($this->owner)
        ->get(route('time.index', ['user_id' => $stranger->id]))
        ->assertInertia(fn ($page) => $page
            ->has('entries.data', 1)
            ->where('entries.data.0.description', 'Owner work')
        );
});

it('never shows entries from another workspace', function () {
    $otherUser = User::factory()->create(['type' => 'freelancer']);
    $otherWorkspace = Workspace::create([
        'name' => 'Other WS',
        'slug' => 'other-ws-team',
        'owner_id' => $otherUser->id,
        'currency' => 'USD',
        'timezone' => 'UTC',
    ]);
    $otherClient = Client::create([
        'workspace_id' => $otherWorkspace->id,
        'name' => 'Globex',
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
    TimeEntry::create([
        'project_id' => $otherProject->id,
        'user_id' => $otherUser->id,
        'description' => 'Other workspace work',
        'started_at' => now()->subHour(),
        'stopped_at' => now(),
        'duration_minutes' => 60,
        'billable' => true,
    ]);

    ($this->logTime)($this->owner, 'Owner work');

    $this->actingAs($this->owner)
        ->get(route('time.index', ['user_id' => 'all']))
        ->assertInertia(fn ($page) => $page->has('entries.data', 1));
});

it('still refuses an owner editing or deleting a member entry', function () {
    $entry = ($this->logTime)($this->member, 'Member work');

    $this->actingAs($this->owner)
        ->put(route('time.update', $entry), [
            'project_id' => $this->project->id,
            'description' => 'Rewritten by owner',
            'started_at' => now()->subHour()->toDateTimeString(),
            'stopped_at' => now()->toDateTimeString(),
        ])->assertForbidden();

    $this->actingAs($this->owner)
        ->delete(route('time.destroy', $entry))
        ->assertForbidden();

    expect($entry->fresh()->description)->toBe('Member work');
});
