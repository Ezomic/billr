<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Workspace;

function freelancerWithoutWorkspace(): User
{
    return User::factory()->create(['type' => 'freelancer', 'current_workspace_id' => null]);
}

function freelancerWithWorkspace(): User
{
    $user = User::factory()->create(['type' => 'freelancer']);

    $workspace = Workspace::create([
        'name' => 'Test Workspace',
        'slug' => 'test-workspace',
        'owner_id' => $user->id,
        'currency' => 'EUR',
        'timezone' => 'UTC',
    ]);
    $workspace->members()->attach($user->id, ['role' => 'owner']);
    $user->update(['current_workspace_id' => $workspace->id]);

    return $user->fresh();
}

it('redirects a workspace-less freelancer to the creation screen', function () {
    $this->actingAs(freelancerWithoutWorkspace())
        ->get(route('dashboard'))
        ->assertRedirect(route('workspaces.create'));
});

it('redirects from every freelancer section, not just the dashboard', function () {
    $user = freelancerWithoutWorkspace();

    foreach (['clients.index', 'projects.index', 'time.index', 'invoices.index', 'settings.profile'] as $name) {
        $this->actingAs($user)
            ->get(route($name))
            ->assertRedirect(route('workspaces.create'));
    }
});

it('lets a workspace-less freelancer reach the creation screen without looping', function () {
    $this->actingAs(freelancerWithoutWorkspace())
        ->get(route('workspaces.create'))
        ->assertOk();
});

it('creates the workspace and lands the user on the dashboard', function () {
    $user = freelancerWithoutWorkspace();

    $this->actingAs($user)
        ->post(route('workspaces.store'), ['name' => 'Acme Freelancing'])
        ->assertRedirect(route('dashboard'));

    expect($user->fresh()->current_workspace_id)->not->toBeNull();

    $this->actingAs($user->fresh())
        ->get(route('dashboard'))
        ->assertOk();
});

it('leaves a freelancer who already has a workspace alone', function () {
    $this->actingAs(freelancerWithWorkspace())
        ->get(route('dashboard'))
        ->assertOk();
});
