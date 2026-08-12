<?php

declare(strict_types=1);

use App\Models\Client;
use App\Models\Project;
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

    $this->actingAs($this->user);
});

it('can list projects', function () {
    Project::create([
        'workspace_id' => $this->workspace->id,
        'client_id' => $this->client->id,
        'name' => 'Website',
        'status' => 'active',
        'type' => 'hourly',
        'hourly_rate' => 9500,
    ]);

    $this->get(route('projects.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('projects/Index')
            ->has('projects', 1)
            ->has('clients', 1)
        );
});

it('can create an hourly project', function () {
    $this->post(route('projects.store'), [
        'client_id' => $this->client->id,
        'name' => 'Retainer',
        'type' => 'hourly',
        'hourly_rate' => 12000,
    ])->assertRedirect();

    $project = Project::first();

    expect($project->name)->toBe('Retainer')
        ->and($project->workspace_id)->toBe($this->workspace->id)
        ->and($project->hourly_rate)->toBe(12000);
});

it('can create a fixed-price project', function () {
    $this->post(route('projects.store'), [
        'client_id' => $this->client->id,
        'name' => 'Rebrand',
        'type' => 'fixed',
        'fixed_price' => 500000,
    ])->assertRedirect();

    expect(Project::first()->fixed_price)->toBe(500000);
});

it('requires a rate matching the project type', function () {
    $this->post(route('projects.store'), [
        'client_id' => $this->client->id,
        'name' => 'No rate',
        'type' => 'hourly',
    ])->assertSessionHasErrors('hourly_rate');

    $this->post(route('projects.store'), [
        'client_id' => $this->client->id,
        'name' => 'No price',
        'type' => 'fixed',
    ])->assertSessionHasErrors('fixed_price');

    expect(Project::count())->toBe(0);
});

it('can update a project', function () {
    $project = Project::create([
        'workspace_id' => $this->workspace->id,
        'client_id' => $this->client->id,
        'name' => 'Old name',
        'status' => 'active',
        'type' => 'hourly',
        'hourly_rate' => 9500,
    ]);

    $this->put(route('projects.update', $project), [
        'client_id' => $this->client->id,
        'name' => 'New name',
        'type' => 'hourly',
        'hourly_rate' => 11000,
    ])->assertRedirect();

    expect($project->fresh()->name)->toBe('New name')
        ->and($project->fresh()->hourly_rate)->toBe(11000);
});

it('can delete a project', function () {
    $project = Project::create([
        'workspace_id' => $this->workspace->id,
        'client_id' => $this->client->id,
        'name' => 'Doomed',
        'status' => 'active',
        'type' => 'hourly',
        'hourly_rate' => 9500,
    ]);

    $this->delete(route('projects.destroy', $project))->assertRedirect();

    expect(Project::count())->toBe(0)
        ->and(Project::withTrashed()->count())->toBe(1);
});

it('archives and restores a project', function () {
    $project = Project::create([
        'workspace_id' => $this->workspace->id,
        'client_id' => $this->client->id,
        'name' => 'Finished work',
        'status' => 'active',
        'type' => 'hourly',
        'hourly_rate' => 9500,
    ]);

    $this->post(route('projects.archive', $project))->assertRedirect();
    expect($project->fresh()->status)->toBe('archived');

    $this->post(route('projects.unarchive', $project))->assertRedirect();
    expect($project->fresh()->status)->toBe('active');
});

it('keeps an archived project out of the timer project list', function () {
    $active = Project::create([
        'workspace_id' => $this->workspace->id,
        'client_id' => $this->client->id,
        'name' => 'Still going',
        'status' => 'active',
        'type' => 'hourly',
        'hourly_rate' => 9500,
    ]);

    $archived = Project::create([
        'workspace_id' => $this->workspace->id,
        'client_id' => $this->client->id,
        'name' => 'Wrapped up',
        'status' => 'active',
        'type' => 'hourly',
        'hourly_rate' => 9500,
    ]);

    $this->post(route('projects.archive', $archived))->assertRedirect();

    $this->get(route('time.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('projects', 1)
            ->where('projects.0.id', $active->id)
        );
});

it('still lets an archived project be invoiced', function () {
    $project = Project::create([
        'workspace_id' => $this->workspace->id,
        'client_id' => $this->client->id,
        'name' => 'Archived but unpaid',
        'status' => 'active',
        'type' => 'fixed',
        'fixed_price' => 150000,
    ]);

    $this->post(route('projects.archive', $project))->assertRedirect();

    $this->getJson(route('invoices.unbilled-projects', ['client_id' => $this->client->id]))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.id', $project->id);
});

it('cannot archive another workspace project', function () {
    $otherUser = User::factory()->create(['type' => 'freelancer']);
    $otherWorkspace = Workspace::create([
        'name' => 'Other WS',
        'slug' => 'other-ws-archive',
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

    $this->post(route('projects.archive', $otherProject))->assertForbidden();

    expect($otherProject->fresh()->status)->toBe('active');
});

it('cannot create a project for a client in another workspace', function () {
    $otherUser = User::factory()->create(['type' => 'freelancer']);
    $otherWorkspace = Workspace::create([
        'name' => 'Other WS',
        'slug' => 'other-ws-projects',
        'owner_id' => $otherUser->id,
        'currency' => 'USD',
        'timezone' => 'UTC',
    ]);
    $otherClient = Client::create([
        'workspace_id' => $otherWorkspace->id,
        'name' => 'Other Client',
        'currency' => 'USD',
    ]);

    $this->post(route('projects.store'), [
        'client_id' => $otherClient->id,
        'name' => 'Sneaky',
        'type' => 'hourly',
        'hourly_rate' => 100,
    ])->assertForbidden();

    expect(Project::count())->toBe(0);
});

it('cannot update or delete another workspace project', function () {
    $otherUser = User::factory()->create(['type' => 'freelancer']);
    $otherWorkspace = Workspace::create([
        'name' => 'Other WS',
        'slug' => 'other-ws-projects-2',
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

    $this->put(route('projects.update', $otherProject), [
        'client_id' => $otherClient->id,
        'name' => 'Hijacked',
        'type' => 'hourly',
        'hourly_rate' => 1,
    ])->assertForbidden();

    $this->delete(route('projects.destroy', $otherProject))->assertForbidden();

    expect($otherProject->fresh()->name)->toBe('Theirs');
});
