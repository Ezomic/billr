<?php

declare(strict_types=1);

use App\Models\Client;
use App\Models\RecurringInvoice;
use App\Models\User;
use App\Models\Workspace;
use Carbon\CarbonImmutable;

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
        'name' => 'Acme',
        'currency' => 'USD',
    ]);

    $this->actingAs($this->user);

    $this->payload = fn (array $overrides = []): array => array_merge([
        'client_id' => $this->client->id,
        'name' => 'Monthly retainer',
        'interval' => 'monthly',
        'start_on' => today()->toDateString(),
        'end_on' => null,
        'tax_rate' => 21,
        'notes' => 'Retainer',
        'lines' => [
            ['description' => 'Retainer', 'quantity' => 1, 'unit_price' => 200000],
        ],
    ], $overrides);
});

it('lists schedules', function () {
    $this->post(route('recurring.store'), ($this->payload)())->assertRedirect();

    $this->get(route('recurring.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('recurring/Index')
            ->has('schedules', 1)
            ->has('clients', 1)
        );
});

it('creates a schedule with its lines', function () {
    $this->post(route('recurring.store'), ($this->payload)([
        'lines' => [
            ['description' => 'Retainer', 'quantity' => 1, 'unit_price' => 200000],
            ['description' => 'Hosting', 'quantity' => 3, 'unit_price' => 1500],
        ],
    ]))->assertRedirect();

    $schedule = RecurringInvoice::with('lines')->first();

    expect($schedule->name)->toBe('Monthly retainer')
        ->and($schedule->status)->toBe('active')
        ->and($schedule->currency)->toBe('USD')
        ->and($schedule->lines)->toHaveCount(2)
        ->and($schedule->lines[1]->amount)->toBe(4500);
});

it('requires at least one line', function () {
    $this->post(route('recurring.store'), ($this->payload)(['lines' => []]))
        ->assertSessionHasErrors('lines');

    expect(RecurringInvoice::count())->toBe(0);
});

it('rejects an end date before the start date', function () {
    $this->post(route('recurring.store'), ($this->payload)([
        'start_on' => today()->toDateString(),
        'end_on' => today()->subDay()->toDateString(),
    ]))->assertSessionHasErrors('end_on');
});

it('rejects an unknown interval', function () {
    $this->post(route('recurring.store'), ($this->payload)(['interval' => 'fortnightly']))
        ->assertSessionHasErrors('interval');
});

it('never schedules the first run in the past', function () {
    $this->post(route('recurring.store'), ($this->payload)([
        'start_on' => today()->subMonths(5)->toDateString(),
    ]))->assertRedirect();

    $schedule = RecurringInvoice::first();

    expect($schedule->next_run_on->isBefore(today()))->toBeFalse();
});

it('advances by the right interval', function () {
    $monthly = new RecurringInvoice(['interval' => 'monthly']);
    $quarterly = new RecurringInvoice(['interval' => 'quarterly']);
    $yearly = new RecurringInvoice(['interval' => 'yearly']);

    $from = CarbonImmutable::parse('2026-01-15');

    expect($monthly->advance($from)->toDateString())->toBe('2026-02-15')
        ->and($quarterly->advance($from)->toDateString())->toBe('2026-04-15')
        ->and($yearly->advance($from)->toDateString())->toBe('2027-01-15');
});

it('updates a schedule and replaces its lines', function () {
    $this->post(route('recurring.store'), ($this->payload)())->assertRedirect();
    $schedule = RecurringInvoice::first();

    $this->put(route('recurring.update', $schedule), ($this->payload)([
        'name' => 'Renamed',
        'interval' => 'quarterly',
        'lines' => [['description' => 'New line', 'quantity' => 2, 'unit_price' => 5000]],
    ]))->assertRedirect();

    $schedule->refresh()->load('lines');

    expect($schedule->name)->toBe('Renamed')
        ->and($schedule->interval)->toBe('quarterly')
        ->and($schedule->lines)->toHaveCount(1)
        ->and($schedule->lines->first()->amount)->toBe(10000);
});

it('pauses and resumes a schedule', function () {
    $this->post(route('recurring.store'), ($this->payload)())->assertRedirect();
    $schedule = RecurringInvoice::first();

    $this->post(route('recurring.pause', $schedule))->assertRedirect();
    expect($schedule->fresh()->status)->toBe('paused')
        ->and($schedule->fresh()->isDue())->toBeFalse();

    $this->post(route('recurring.resume', $schedule))->assertRedirect();
    expect($schedule->fresh()->status)->toBe('active');
});

it('treats a schedule past its end date as finished', function () {
    $this->post(route('recurring.store'), ($this->payload)([
        'start_on' => today()->subMonths(6)->toDateString(),
        'end_on' => today()->subMonth()->toDateString(),
    ]))->assertRedirect();

    $schedule = RecurringInvoice::first();

    expect($schedule->hasFinished())->toBeTrue()
        ->and($schedule->isDue())->toBeFalse();
});

it('soft deletes a schedule', function () {
    $this->post(route('recurring.store'), ($this->payload)())->assertRedirect();
    $schedule = RecurringInvoice::first();

    $this->delete(route('recurring.destroy', $schedule))->assertRedirect();

    expect(RecurringInvoice::count())->toBe(0)
        ->and(RecurringInvoice::withTrashed()->count())->toBe(1);
});

it('cannot use a client from another workspace', function () {
    $otherUser = User::factory()->create(['type' => 'freelancer']);
    $otherWorkspace = Workspace::create([
        'name' => 'Other WS',
        'slug' => 'other-ws-recurring',
        'owner_id' => $otherUser->id,
        'currency' => 'USD',
        'timezone' => 'UTC',
    ]);
    $otherClient = Client::create([
        'workspace_id' => $otherWorkspace->id,
        'name' => 'Globex',
        'currency' => 'USD',
    ]);

    $this->post(route('recurring.store'), ($this->payload)(['client_id' => $otherClient->id]))
        ->assertNotFound();

    expect(RecurringInvoice::count())->toBe(0);
});

it('cannot touch another workspace schedule', function () {
    $otherUser = User::factory()->create(['type' => 'freelancer']);
    $otherWorkspace = Workspace::create([
        'name' => 'Other WS',
        'slug' => 'other-ws-recurring-2',
        'owner_id' => $otherUser->id,
        'currency' => 'USD',
        'timezone' => 'UTC',
    ]);
    $otherClient = Client::create([
        'workspace_id' => $otherWorkspace->id,
        'name' => 'Globex',
        'currency' => 'USD',
    ]);
    $otherSchedule = $otherWorkspace->recurringInvoices()->create([
        'client_id' => $otherClient->id,
        'created_by' => $otherUser->id,
        'name' => 'Theirs',
        'interval' => 'monthly',
        'start_on' => today(),
        'next_run_on' => today(),
        'currency' => 'USD',
        'tax_rate' => 0,
        'status' => 'active',
    ]);

    $this->post(route('recurring.pause', $otherSchedule))->assertForbidden();
    $this->delete(route('recurring.destroy', $otherSchedule))->assertForbidden();

    expect($otherSchedule->fresh()->status)->toBe('active');
});
