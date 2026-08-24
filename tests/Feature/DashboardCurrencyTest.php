<?php

declare(strict_types=1);

use App\Models\Client;
use App\Models\Invoice;
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

    $this->number = 0;

    $this->invoice = function (string $currency, int $total, string $status, ?string $paidAt = null): Invoice {
        $this->number++;

        $client = Client::create([
            'workspace_id' => $this->workspace->id,
            'name' => $currency.'Co '.$this->number,
            'currency' => $currency,
        ]);

        return Invoice::create([
            'workspace_id' => $this->workspace->id,
            'client_id' => $client->id,
            'created_by' => $this->user->id,
            'number' => 'INV-2026-'.str_pad((string) $this->number, 4, '0', STR_PAD_LEFT),
            'status' => $status,
            'currency' => $currency,
            'subtotal' => $total,
            'tax_amount' => 0,
            'total' => $total,
            'tax_rate' => 0,
            'paid_at' => $paidAt,
        ]);
    };

    $this->actingAs($this->user);
});

it('reports outstanding money per currency rather than as one sum', function () {
    ($this->invoice)('EUR', 100000, 'sent');
    ($this->invoice)('USD', 100000, 'sent');

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('outstanding', 2)
            ->where('outstanding.0', ['currency' => 'EUR', 'total' => 100000])
            ->where('outstanding.1', ['currency' => 'USD', 'total' => 100000])
        );
});

it('adds up several invoices within the same currency', function () {
    ($this->invoice)('EUR', 100000, 'sent');
    ($this->invoice)('EUR', 50000, 'overdue');
    ($this->invoice)('USD', 25000, 'draft');

    $this->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('outstanding', 2)
            ->where('outstanding.0', ['currency' => 'EUR', 'total' => 150000])
            ->where('outstanding.1', ['currency' => 'USD', 'total' => 25000])
        );
});

it('leaves paid and void invoices out of outstanding', function () {
    ($this->invoice)('EUR', 100000, 'paid', now()->toDateTimeString());
    ($this->invoice)('EUR', 100000, 'void');
    ($this->invoice)('EUR', 30000, 'sent');

    $this->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('outstanding', 1)
            ->where('outstanding.0', ['currency' => 'EUR', 'total' => 30000])
        );
});

it('reports paid this month per currency', function () {
    ($this->invoice)('EUR', 100000, 'paid', now()->toDateTimeString());
    ($this->invoice)('USD', 40000, 'paid', now()->toDateTimeString());
    ($this->invoice)('EUR', 999999, 'paid', now()->subMonths(2)->toDateTimeString());

    $this->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('paidThisMonth', 2)
            ->where('paidThisMonth.0', ['currency' => 'EUR', 'total' => 100000])
            ->where('paidThisMonth.1', ['currency' => 'USD', 'total' => 40000])
        );
});

it('gives an empty workspace nothing to report', function () {
    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('outstanding', 0)
            ->has('paidThisMonth', 0)
            ->where('workspaceCurrency', 'EUR')
            ->where('stats.totalInvoices', 0)
        );
});

it('still counts overdue invoices across currencies', function () {
    ($this->invoice)('EUR', 100000, 'overdue');
    ($this->invoice)('USD', 100000, 'overdue');

    $this->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('stats.overdueCount', 2));
});

it('never counts another workspace money', function () {
    ($this->invoice)('EUR', 100000, 'sent');

    $otherUser = User::factory()->create(['type' => 'freelancer']);
    $otherWorkspace = Workspace::create([
        'name' => 'Other',
        'slug' => 'other-ws-dash',
        'owner_id' => $otherUser->id,
        'currency' => 'EUR',
        'timezone' => 'UTC',
    ]);
    $otherClient = Client::create([
        'workspace_id' => $otherWorkspace->id,
        'name' => 'Theirs',
        'currency' => 'EUR',
    ]);
    Invoice::create([
        'workspace_id' => $otherWorkspace->id,
        'client_id' => $otherClient->id,
        'created_by' => $otherUser->id,
        'number' => 'INV-2026-9999',
        'status' => 'sent',
        'currency' => 'EUR',
        'subtotal' => 500000,
        'tax_amount' => 0,
        'total' => 500000,
        'tax_rate' => 0,
    ]);

    $this->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('outstanding', 1)
            ->where('outstanding.0', ['currency' => 'EUR', 'total' => 100000])
        );
});
