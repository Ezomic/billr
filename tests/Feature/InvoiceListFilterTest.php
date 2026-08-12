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
        'currency' => 'USD',
        'timezone' => 'UTC',
    ]);
    $this->workspace->members()->attach($this->user->id, ['role' => 'owner']);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);

    $this->acme = Client::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Acme',
        'currency' => 'USD',
    ]);
    $this->globex = Client::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Globex',
        'currency' => 'USD',
    ]);

    $this->makeInvoice = function (Client $client, string $status, string $number): Invoice {
        return Invoice::create([
            'workspace_id' => $this->workspace->id,
            'client_id' => $client->id,
            'created_by' => $this->user->id,
            'number' => $number,
            'status' => $status,
            'currency' => 'USD',
            'subtotal' => 10000,
            'tax_amount' => 0,
            'total' => 10000,
            'tax_rate' => 0,
        ]);
    };

    $this->actingAs($this->user);
});

it('paginates instead of loading every invoice', function () {
    foreach (range(1, 30) as $n) {
        ($this->makeInvoice)($this->acme, 'draft', 'INV-2026-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT));
    }

    $this->get(route('invoices.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('invoices/Index')
            ->has('invoices.data', 25)
            ->where('invoices.total', 30)
            ->where('invoices.last_page', 2)
        );

    $this->get(route('invoices.index', ['page' => 2]))
        ->assertInertia(fn ($page) => $page->has('invoices.data', 5));
});

it('filters by status', function () {
    ($this->makeInvoice)($this->acme, 'draft', 'INV-2026-0001');
    ($this->makeInvoice)($this->acme, 'paid', 'INV-2026-0002');
    ($this->makeInvoice)($this->acme, 'paid', 'INV-2026-0003');

    $this->get(route('invoices.index', ['status' => 'paid']))
        ->assertInertia(fn ($page) => $page
            ->has('invoices.data', 2)
            ->where('filters.status', 'paid')
        );
});

it('filters by client', function () {
    ($this->makeInvoice)($this->acme, 'draft', 'INV-2026-0001');
    ($this->makeInvoice)($this->globex, 'draft', 'INV-2026-0002');

    $this->get(route('invoices.index', ['client_id' => $this->globex->id]))
        ->assertInertia(fn ($page) => $page
            ->has('invoices.data', 1)
            ->where('invoices.data.0.number', 'INV-2026-0002')
        );
});

it('searches by invoice number', function () {
    ($this->makeInvoice)($this->acme, 'draft', 'INV-2026-0042');
    ($this->makeInvoice)($this->acme, 'draft', 'INV-2026-0099');

    $this->get(route('invoices.index', ['q' => '0042']))
        ->assertInertia(fn ($page) => $page
            ->has('invoices.data', 1)
            ->where('invoices.data.0.number', 'INV-2026-0042')
        );
});

it('combines filters', function () {
    ($this->makeInvoice)($this->acme, 'paid', 'INV-2026-0001');
    ($this->makeInvoice)($this->acme, 'draft', 'INV-2026-0002');
    ($this->makeInvoice)($this->globex, 'paid', 'INV-2026-0003');

    $this->get(route('invoices.index', ['status' => 'paid', 'client_id' => $this->acme->id]))
        ->assertInertia(fn ($page) => $page
            ->has('invoices.data', 1)
            ->where('invoices.data.0.number', 'INV-2026-0001')
        );
});

it('ignores an unknown status rather than returning nothing', function () {
    ($this->makeInvoice)($this->acme, 'draft', 'INV-2026-0001');

    $this->get(route('invoices.index', ['status' => 'nonsense']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('invoices.data', 1)
            ->where('filters.status', '')
        );
});

it('keeps the filters on the pagination links', function () {
    foreach (range(1, 30) as $n) {
        ($this->makeInvoice)($this->acme, 'draft', 'INV-2026-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT));
    }

    $this->get(route('invoices.index', ['status' => 'draft']))
        ->assertInertia(fn ($page) => $page
            ->where('invoices.next_page_url', fn ($url) => str_contains((string) $url, 'status=draft'))
        );
});

it('never lists another workspace invoice', function () {
    $otherUser = User::factory()->create(['type' => 'freelancer']);
    $otherWorkspace = Workspace::create([
        'name' => 'Other WS',
        'slug' => 'other-ws-list',
        'owner_id' => $otherUser->id,
        'currency' => 'USD',
        'timezone' => 'UTC',
    ]);
    $otherClient = Client::create([
        'workspace_id' => $otherWorkspace->id,
        'name' => 'Other Client',
        'currency' => 'USD',
    ]);
    Invoice::create([
        'workspace_id' => $otherWorkspace->id,
        'client_id' => $otherClient->id,
        'created_by' => $otherUser->id,
        'number' => 'INV-2026-7000',
        'status' => 'draft',
        'currency' => 'USD',
        'subtotal' => 0,
        'tax_amount' => 0,
        'total' => 0,
        'tax_rate' => 0,
    ]);

    ($this->makeInvoice)($this->acme, 'draft', 'INV-2026-0001');

    $this->get(route('invoices.index', ['client_id' => $otherClient->id]))
        ->assertInertia(fn ($page) => $page->has('invoices.data', 0));
});
