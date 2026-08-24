<?php

declare(strict_types=1);

use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    $owner = User::factory()->create(['type' => 'freelancer']);

    $this->workspace = Workspace::create([
        'name' => 'Test Workspace',
        'slug' => 'test-workspace',
        'owner_id' => $owner->id,
        'currency' => 'USD',
        'timezone' => 'UTC',
    ]);

    $this->client = Client::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Acme',
        'currency' => 'USD',
    ]);

    $this->portalUser = User::factory()->create(['type' => 'client']);
    $this->client->portalUsers()->attach($this->portalUser->id);

    $this->number = 0;

    $this->invoiceFor = function (Client $client, string $status = 'sent'): Invoice {
        $this->number++;

        return Invoice::create([
            'workspace_id' => $client->workspace_id,
            'client_id' => $client->id,
            'created_by' => $this->workspace->owner_id,
            'number' => 'INV-2026-'.str_pad((string) $this->number, 4, '0', STR_PAD_LEFT),
            'status' => $status,
            'currency' => 'USD',
            'subtotal' => 10000,
            'tax_amount' => 0,
            'total' => 10000,
            'tax_rate' => 0,
            'issued_at' => today()->subDays($this->number),
        ]);
    };

    $this->actingAs($this->portalUser);
});

it('paginates instead of loading every invoice the client ever had', function () {
    foreach (range(1, 30) as $ignored) {
        ($this->invoiceFor)($this->client);
    }

    $this->get(route('portal.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('portal/Dashboard')
            ->has('invoices.data', 25)
            ->where('invoices.total', 30)
            ->where('invoices.last_page', 2)
        );

    $this->get(route('portal.dashboard', ['page' => 2]))
        ->assertInertia(fn ($page) => $page->has('invoices.data', 5));
});

it('filters by status', function () {
    ($this->invoiceFor)($this->client, 'paid');
    ($this->invoiceFor)($this->client, 'sent');
    ($this->invoiceFor)($this->client, 'sent');

    $this->get(route('portal.dashboard', ['status' => 'sent']))
        ->assertInertia(fn ($page) => $page
            ->has('invoices.data', 2)
            ->where('filters.status', 'sent')
        );
});

it('ignores an unknown status rather than returning nothing', function () {
    ($this->invoiceFor)($this->client);

    $this->get(route('portal.dashboard', ['status' => 'nonsense']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('invoices.data', 1)
            ->where('filters.status', '')
        );
});

it('keeps the filter on the pagination links', function () {
    foreach (range(1, 30) as $ignored) {
        ($this->invoiceFor)($this->client, 'sent');
    }

    $this->get(route('portal.dashboard', ['status' => 'sent']))
        ->assertInertia(fn ($page) => $page
            ->where('invoices.next_page_url', fn ($url) => str_contains((string) $url, 'status=sent'))
        );
});

it('never shows a client invoices they have no access to', function () {
    ($this->invoiceFor)($this->client);

    $strangerClient = Client::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Globex',
        'currency' => 'USD',
    ]);
    ($this->invoiceFor)($strangerClient);

    $this->get(route('portal.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('invoices.data', 1)
            ->where('invoices.data.0.client.name', 'Acme')
        );
});
