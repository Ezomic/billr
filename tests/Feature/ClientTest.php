<?php

declare(strict_types=1);

use App\Models\Client;
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

    $this->actingAs($this->user);
});

it('can list clients', function () {
    Client::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Acme Corp',
        'currency' => 'USD',
    ]);

    $this->get(route('clients.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('clients/Index')
            ->has('clients', 1)
        );
});

it('can create a client', function () {
    $this->post(route('clients.store'), [
        'name' => 'New Client',
        'email' => 'client@example.com',
        'currency' => 'EUR',
    ])->assertRedirect();

    expect(Client::where('name', 'New Client')->exists())->toBeTrue();
});

it('can update a client', function () {
    $client = Client::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Old Name',
        'currency' => 'USD',
    ]);

    $this->put(route('clients.update', $client), [
        'name' => 'New Name',
        'currency' => 'USD',
    ])->assertRedirect();

    expect($client->fresh()->name)->toBe('New Name');
});

it('can delete a client', function () {
    $client = Client::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'To Delete',
        'currency' => 'USD',
    ]);

    $this->delete(route('clients.destroy', $client))->assertRedirect();

    expect(Client::find($client->id))->toBeNull();
});

it('cannot access another workspace client', function () {
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
        'name' => 'Hidden Client',
        'currency' => 'USD',
    ]);

    $this->delete(route('clients.destroy', $otherClient))->assertForbidden();
});
