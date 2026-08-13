<?php

declare(strict_types=1);

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Testing\TestResponse;

function csvBody(TestResponse $response): string
{
    return ltrim($response->streamedContent(), "\xEF\xBB\xBF");
}

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

    $this->project = Project::create([
        'workspace_id' => $this->workspace->id,
        'client_id' => $this->client->id,
        'name' => 'Website',
        'status' => 'active',
        'type' => 'hourly',
        'hourly_rate' => 10000,
    ]);

    $this->actingAs($this->user);
});

it('exports invoices as csv', function () {
    Invoice::create([
        'workspace_id' => $this->workspace->id,
        'client_id' => $this->client->id,
        'created_by' => $this->user->id,
        'number' => 'INV-2026-0001',
        'status' => 'paid',
        'currency' => 'USD',
        'subtotal' => 10000,
        'tax_amount' => 2100,
        'total' => 12100,
        'tax_rate' => 21,
        'issued_at' => today(),
        'due_at' => today()->addDays(30),
    ]);

    $response = $this->get(route('invoices.export'));

    $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $body = csvBody($response);

    expect($body)->toContain('Number,Client,Status,Issued,Due,Paid,Currency,Subtotal,Tax,Total')
        ->and($body)->toContain('INV-2026-0001')
        ->and($body)->toContain('Acme')
        ->and($body)->toContain('100.00')
        ->and($body)->toContain('121.00');
});

it('applies the list filters to the invoice export', function () {
    foreach (['draft' => 'INV-2026-0001', 'paid' => 'INV-2026-0002'] as $status => $number) {
        Invoice::create([
            'workspace_id' => $this->workspace->id,
            'client_id' => $this->client->id,
            'created_by' => $this->user->id,
            'number' => $number,
            'status' => $status,
            'currency' => 'USD',
            'subtotal' => 10000,
            'tax_amount' => 0,
            'total' => 10000,
            'tax_rate' => 0,
        ]);
    }

    $body = csvBody($this->get(route('invoices.export', ['status' => 'paid'])));

    expect($body)->toContain('INV-2026-0002')
        ->and($body)->not->toContain('INV-2026-0001');
});

it('writes a utf-8 bom so spreadsheets read the encoding', function () {
    $response = $this->get(route('invoices.export'));

    expect($response->streamedContent())->toStartWith("\xEF\xBB\xBF");
});

it('never exports another workspace invoices', function () {
    $otherUser = User::factory()->create(['type' => 'freelancer']);
    $otherWorkspace = Workspace::create([
        'name' => 'Other WS',
        'slug' => 'other-ws-export',
        'owner_id' => $otherUser->id,
        'currency' => 'USD',
        'timezone' => 'UTC',
    ]);
    $otherClient = Client::create([
        'workspace_id' => $otherWorkspace->id,
        'name' => 'Globex',
        'currency' => 'USD',
    ]);
    Invoice::create([
        'workspace_id' => $otherWorkspace->id,
        'client_id' => $otherClient->id,
        'created_by' => $otherUser->id,
        'number' => 'INV-2026-9999',
        'status' => 'draft',
        'currency' => 'USD',
        'subtotal' => 0,
        'tax_amount' => 0,
        'total' => 0,
        'tax_rate' => 0,
    ]);

    $body = csvBody($this->get(route('invoices.export')));

    expect($body)->not->toContain('INV-2026-9999')
        ->and($body)->not->toContain('Globex');
});

it('exports time entries as csv with billed status', function () {
    $billed = TimeEntry::create([
        'project_id' => $this->project->id,
        'user_id' => $this->user->id,
        'description' => 'Billed work',
        'started_at' => now()->subHours(3),
        'stopped_at' => now()->subHours(2),
        'duration_minutes' => 60,
        'hourly_rate' => 10000,
        'billable' => true,
    ]);

    TimeEntry::create([
        'project_id' => $this->project->id,
        'user_id' => $this->user->id,
        'description' => 'Unbilled work',
        'started_at' => now()->subHour(),
        'stopped_at' => now(),
        'duration_minutes' => 30,
        'hourly_rate' => 10000,
        'billable' => true,
    ]);

    $invoice = Invoice::create([
        'workspace_id' => $this->workspace->id,
        'client_id' => $this->client->id,
        'created_by' => $this->user->id,
        'number' => 'INV-2026-0007',
        'status' => 'sent',
        'currency' => 'USD',
        'subtotal' => 10000,
        'tax_amount' => 0,
        'total' => 10000,
        'tax_rate' => 0,
    ]);
    $invoice->timeEntries()->attach($billed->id);

    $response = $this->get(route('time.export'));
    $response->assertOk();

    $body = csvBody($response);

    expect($body)->toContain('Date,Client,Project,Description,Minutes,Hours,Rate,Amount,Billable,Billed,Invoice')
        ->and($body)->toContain('Billed work')
        ->and($body)->toContain('Unbilled work')
        ->and($body)->toContain('INV-2026-0007')
        ->and($body)->toContain('1.00')
        ->and($body)->toContain('0.50');
});

it('never exports another user time entries', function () {
    $otherUser = User::factory()->create(['type' => 'freelancer']);

    TimeEntry::create([
        'project_id' => $this->project->id,
        'user_id' => $otherUser->id,
        'description' => 'Not mine',
        'started_at' => now()->subHour(),
        'stopped_at' => now(),
        'duration_minutes' => 60,
        'hourly_rate' => 10000,
        'billable' => true,
    ]);

    $body = csvBody($this->get(route('time.export')));

    expect($body)->not->toContain('Not mine');
});
