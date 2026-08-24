<?php

declare(strict_types=1);

use App\Models\Client;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    $this->owner = User::factory()->create(['type' => 'freelancer']);
    $this->member = User::factory()->create(['type' => 'freelancer']);

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

    $this->remove = fn () => test()->actingAs($this->owner)
        ->delete(route('settings.members.remove', $this->member))
        ->assertRedirect();
});

it('stops a removed member reading the workspace', function () {
    ($this->remove)();

    $this->actingAs($this->member->fresh())
        ->get(route('clients.index'))
        ->assertRedirect(route('workspaces.create'));
});

it('stops a removed member writing to the workspace', function () {
    ($this->remove)();

    $this->actingAs($this->member->fresh())
        ->post(route('clients.store'), ['name' => 'Added by removed member'])
        ->assertRedirect(route('workspaces.create'));

    expect(Client::where('name', 'Added by removed member')->exists())->toBeFalse();
});

it('locks a removed member out of every freelancer section', function () {
    ($this->remove)();

    $member = $this->member->fresh();

    foreach (['dashboard', 'clients.index', 'projects.index', 'time.index', 'invoices.index', 'recurring.index'] as $name) {
        $this->actingAs($member)
            ->get(route($name))
            ->assertRedirect(route('workspaces.create'));
    }
});

it('clears the removed member pointer at the workspace', function () {
    ($this->remove)();

    expect($this->member->fresh()->current_workspace_id)->toBeNull();
});

it('moves a removed member to another workspace they are still in', function () {
    $other = Workspace::create([
        'name' => 'Second',
        'slug' => 'second-ws',
        'owner_id' => $this->member->id,
        'currency' => 'USD',
        'timezone' => 'UTC',
    ]);
    $other->members()->attach($this->member->id, ['role' => 'owner']);

    ($this->remove)();

    expect($this->member->fresh()->current_workspace_id)->toBe($other->id);
});

it('stops a removed member ingesting time over the api', function () {
    Project::create([
        'workspace_id' => $this->workspace->id,
        'client_id' => $this->client->id,
        'name' => 'Website',
        'status' => 'active',
        'type' => 'hourly',
        'hourly_rate' => 10000,
    ]);

    $token = $this->member->createToken('sync', ['time-entries:create'])->plainTextToken;

    // Removal is applied directly rather than through the endpoint on purpose.
    // Calling it via actingAs() would leave a session behind, and Sanctum falls
    // back to the session guard, so the request below would authenticate as the
    // owner and pass no matter what the token says. remove() clearing the
    // pointer is covered by its own test above.
    $this->workspace->members()->detach($this->member->id);
    $this->member->forceFill(['current_workspace_id' => null])->save();

    // The API never passes through EnsureWorkspace, so this has to be refused
    // by the workspace resolution itself rather than by the middleware.
    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/time-entries', [
            'external_source' => 'chronos',
            'external_ref' => 'evt-1',
            'minutes' => 60,
            'spent_on' => today()->toDateString(),
            'client_name' => 'Acme',
            'project_name' => 'Website',
        ])
        ->assertForbidden();

    expect(TimeEntry::count())->toBe(0);
});

it('leaves a member who is still in the workspace alone', function () {
    $this->actingAs($this->member)
        ->get(route('clients.index'))
        ->assertOk();
});

it('does not disturb the pointer of a member removed from a different workspace', function () {
    $other = Workspace::create([
        'name' => 'Second',
        'slug' => 'second-ws-2',
        'owner_id' => $this->owner->id,
        'currency' => 'USD',
        'timezone' => 'UTC',
    ]);
    $other->members()->attach($this->owner->id, ['role' => 'owner']);
    $other->members()->attach($this->member->id, ['role' => 'member']);

    // Owner is acting in the second workspace and removes the member there.
    $this->owner->update(['current_workspace_id' => $other->id]);

    $this->actingAs($this->owner)
        ->delete(route('settings.members.remove', $this->member))
        ->assertRedirect();

    // The member was acting in the first workspace, which they still belong to.
    expect($this->member->fresh()->current_workspace_id)->toBe($this->workspace->id);

    $this->actingAs($this->member->fresh())
        ->get(route('clients.index'))
        ->assertOk();
});
