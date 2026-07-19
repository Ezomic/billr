<?php

declare(strict_types=1);

use App\Models\Client;
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

    $this->token = $this->user->createToken('tracker', ['time-entries:create'])->plainTextToken;
});

it('creates a client, project, and time entry from a matched-by-name payload', function () {
    $response = $this->withToken($this->token)->postJson('/api/time-entries', [
        'external_source' => 'tracker',
        'external_ref' => 'CMS-42',
        'client_name' => 'Acme BV',
        'project_name' => 'Portfolio CMS',
        'minutes' => 90,
        'spent_on' => '2026-07-19',
        'description' => 'CMS-42: Fix date format',
        'billable' => true,
    ]);

    $response->assertCreated();

    $client = Client::where('name', 'Acme BV')->first();
    $project = Project::where('name', 'Portfolio CMS')->first();
    $entry = TimeEntry::where('external_ref', 'CMS-42')->first();

    expect($client)->not->toBeNull()
        ->and($project)->not->toBeNull()
        ->and($project->client_id)->toBe($client->id)
        ->and($entry)->not->toBeNull()
        ->and($entry->duration_minutes)->toBe(90)
        ->and($entry->billable)->toBeTrue();

    $response->assertJson([
        'time_entry_id' => $entry->id,
        'project_id' => $project->id,
        'client_id' => $client->id,
        'billable' => true,
    ]);
});

it('upserts on external_ref instead of creating a duplicate', function () {
    $this->withToken($this->token)->postJson('/api/time-entries', [
        'external_source' => 'tracker',
        'external_ref' => 'CMS-42',
        'client_name' => 'Acme BV',
        'project_name' => 'Portfolio CMS',
        'minutes' => 90,
        'spent_on' => '2026-07-19',
    ]);

    $this->withToken($this->token)->postJson('/api/time-entries', [
        'external_source' => 'tracker',
        'external_ref' => 'CMS-42',
        'client_name' => 'Acme BV',
        'project_name' => 'Portfolio CMS',
        'minutes' => 150,
        'spent_on' => '2026-07-19',
    ])->assertCreated();

    expect(TimeEntry::where('external_ref', 'CMS-42')->count())->toBe(1)
        ->and(TimeEntry::where('external_ref', 'CMS-42')->first()->duration_minutes)->toBe(150)
        ->and(Client::count())->toBe(1)
        ->and(Project::count())->toBe(1);
});

it('skips name matching when a known project id is given', function () {
    $client = Client::create(['workspace_id' => $this->workspace->id, 'name' => 'Acme BV']);
    $project = Project::create([
        'workspace_id' => $this->workspace->id,
        'client_id' => $client->id,
        'name' => 'Portfolio CMS',
        'type' => 'hourly',
        'status' => 'active',
    ]);

    $this->withToken($this->token)->postJson('/api/time-entries', [
        'external_source' => 'tracker',
        'external_ref' => 'CMS-43',
        'billr_project_id' => $project->id,
        'minutes' => 30,
        'spent_on' => '2026-07-19',
    ])->assertCreated();

    expect(Client::count())->toBe(1)
        ->and(Project::count())->toBe(1)
        ->and(TimeEntry::first()->project_id)->toBe($project->id);
});

it('rejects a token without the required ability', function () {
    $token = $this->user->createToken('tracker', ['other:ability'])->plainTextToken;

    $this->withToken($token)->postJson('/api/time-entries', [
        'external_source' => 'tracker',
        'external_ref' => 'CMS-42',
        'client_name' => 'Acme BV',
        'project_name' => 'Portfolio CMS',
        'minutes' => 90,
        'spent_on' => '2026-07-19',
    ])->assertForbidden();
});

it('requires authentication', function () {
    $this->postJson('/api/time-entries', [
        'external_source' => 'tracker',
        'external_ref' => 'CMS-42',
        'client_name' => 'Acme BV',
        'project_name' => 'Portfolio CMS',
        'minutes' => 90,
        'spent_on' => '2026-07-19',
    ])->assertUnauthorized();
});

it('requires client and project name when no project id is given', function () {
    $this->withToken($this->token)->postJson('/api/time-entries', [
        'external_source' => 'tracker',
        'external_ref' => 'CMS-42',
        'minutes' => 90,
        'spent_on' => '2026-07-19',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['client_name', 'project_name']);
});
