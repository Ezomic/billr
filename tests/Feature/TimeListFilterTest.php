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
        'currency' => 'EUR',
        'timezone' => 'UTC',
    ]);
    $this->workspace->members()->attach($this->user->id, ['role' => 'owner']);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);

    $this->client = Client::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Acme',
        'currency' => 'EUR',
    ]);

    $this->alpha = Project::create([
        'workspace_id' => $this->workspace->id,
        'client_id' => $this->client->id,
        'name' => 'Alpha',
        'status' => 'active',
        'type' => 'hourly',
        'hourly_rate' => 10000,
    ]);

    $this->beta = Project::create([
        'workspace_id' => $this->workspace->id,
        'client_id' => $this->client->id,
        'name' => 'Beta',
        'status' => 'active',
        'type' => 'hourly',
        'hourly_rate' => 5000,
    ]);

    $this->log = function (Project $project, string $date, int $minutes): TimeEntry {
        return TimeEntry::create([
            'project_id' => $project->id,
            'user_id' => $this->user->id,
            'started_at' => $date.' 09:00:00',
            'stopped_at' => $date.' 10:00:00',
            'duration_minutes' => $minutes,
            'hourly_rate' => $project->hourly_rate,
            'billable' => true,
        ]);
    };

    $this->actingAs($this->user);
});

it('filters by project', function () {
    ($this->log)($this->alpha, '2026-08-01', 60);
    ($this->log)($this->beta, '2026-08-02', 60);

    $this->get(route('time.index', ['project_id' => $this->alpha->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('entries.data', 1)
            ->where('entries.data.0.project.name', 'Alpha')
        );
});

it('filters by date range inclusively', function () {
    ($this->log)($this->alpha, '2026-08-01', 60);
    ($this->log)($this->alpha, '2026-08-05', 60);
    ($this->log)($this->alpha, '2026-08-10', 60);

    $this->get(route('time.index', ['from' => '2026-08-05', 'to' => '2026-08-10']))
        ->assertInertia(fn ($page) => $page->has('entries.data', 2));

    $this->get(route('time.index', ['from' => '2026-08-05', 'to' => '2026-08-05']))
        ->assertInertia(fn ($page) => $page->has('entries.data', 1));
});

it('totals the whole filtered set rather than the visible page', function () {
    ($this->log)($this->alpha, '2026-08-01', 60);
    ($this->log)($this->alpha, '2026-08-02', 30);
    ($this->log)($this->beta, '2026-08-03', 120);

    // Alpha only: 90 minutes at 100.00/hr = 150.00
    $this->get(route('time.index', ['project_id' => $this->alpha->id]))
        ->assertInertia(fn ($page) => $page
            ->where('totals.minutes', 90)
            ->where('totals.amount', 15000)
        );

    // Everything: 90 at 100.00 plus 120 at 50.00 = 150.00 + 100.00
    $this->get(route('time.index'))
        ->assertInertia(fn ($page) => $page
            ->where('totals.minutes', 210)
            ->where('totals.amount', 25000)
        );
});

it('counts every page of a filtered set in the total', function () {
    foreach (range(1, 60) as $ignored) {
        ($this->log)($this->alpha, '2026-08-01', 60);
    }

    $this->get(route('time.index'))
        ->assertInertia(fn ($page) => $page
            ->has('entries.data', 50)
            ->where('totals.minutes', 3600)
        );
});

it('ignores a project from another workspace', function () {
    $otherUser = User::factory()->create(['type' => 'freelancer']);
    $otherWorkspace = Workspace::create([
        'name' => 'Other',
        'slug' => 'other-ws-time-filter',
        'owner_id' => $otherUser->id,
        'currency' => 'EUR',
        'timezone' => 'UTC',
    ]);
    $otherClient = Client::create([
        'workspace_id' => $otherWorkspace->id,
        'name' => 'Globex',
        'currency' => 'EUR',
    ]);
    $otherProject = Project::create([
        'workspace_id' => $otherWorkspace->id,
        'client_id' => $otherClient->id,
        'name' => 'Theirs',
        'status' => 'active',
        'type' => 'hourly',
        'hourly_rate' => 9000,
    ]);

    ($this->log)($this->alpha, '2026-08-01', 60);

    // Falls back to unfiltered rather than leaking or returning nothing.
    $this->get(route('time.index', ['project_id' => $otherProject->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('entries.data', 1)
            ->where('filters.project_id', '')
        );
});

it('ignores a malformed date', function () {
    ($this->log)($this->alpha, '2026-08-01', 60);

    $this->get(route('time.index', ['from' => 'not-a-date']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('entries.data', 1)
            ->where('filters.from', '')
        );
});

it('applies the same filters to the csv export', function () {
    ($this->log)($this->alpha, '2026-08-01', 60);
    ($this->log)($this->beta, '2026-08-02', 60);

    $response = $this->get(route('time.export', ['project_id' => $this->alpha->id]));
    $response->assertOk();

    $body = $response->streamedContent();

    expect($body)->toContain('Alpha')
        ->and($body)->not->toContain('Beta');
});

it('keeps the filters on the pagination links', function () {
    foreach (range(1, 60) as $ignored) {
        ($this->log)($this->alpha, '2026-08-01', 60);
    }

    $this->get(route('time.index', ['project_id' => $this->alpha->id]))
        ->assertInertia(fn ($page) => $page
            ->where('entries.next_page_url', fn ($url) => str_contains((string) $url, 'project_id='))
        );
});
