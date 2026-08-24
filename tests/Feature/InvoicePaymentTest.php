<?php

declare(strict_types=1);

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoicePayment;
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

    $this->invoice = Invoice::create([
        'workspace_id' => $this->workspace->id,
        'client_id' => $this->client->id,
        'created_by' => $this->user->id,
        'number' => 'INV-2026-0001',
        'status' => 'sent',
        'currency' => 'EUR',
        'subtotal' => 100000,
        'tax_amount' => 0,
        'total' => 100000,
        'tax_rate' => 0,
        'issued_at' => today(),
        'due_at' => today()->addDays(30),
    ]);

    $this->pay = fn (int $amount) => test()->post(route('invoices.payments.store', $this->invoice), [
        'amount' => $amount,
        'paid_on' => today()->toDateString(),
        'method' => 'bank',
    ]);

    $this->actingAs($this->user);
});

it('records a partial payment and leaves the invoice unpaid', function () {
    ($this->pay)(40000)->assertRedirect();

    $invoice = $this->invoice->fresh();

    expect($invoice->amountPaid())->toBe(40000)
        ->and($invoice->balance())->toBe(60000)
        ->and($invoice->status)->toBe('sent')
        ->and($invoice->paid_at)->toBeNull();
});

it('settles the invoice once the payments cover the total', function () {
    ($this->pay)(40000)->assertRedirect();
    ($this->pay)(60000)->assertRedirect();

    $invoice = $this->invoice->fresh();

    expect($invoice->balance())->toBe(0)
        ->and($invoice->status)->toBe('paid')
        ->and($invoice->paid_at)->not->toBeNull();
});

it('refuses a payment larger than the outstanding balance', function () {
    ($this->pay)(40000)->assertRedirect();

    ($this->pay)(70000)->assertSessionHasErrors('amount');

    expect($this->invoice->fresh()->amountPaid())->toBe(40000);
});

it('reopens the invoice when a payment is removed', function () {
    ($this->pay)(100000)->assertRedirect();
    expect($this->invoice->fresh()->status)->toBe('paid');

    $payment = InvoicePayment::first();

    $this->delete(route('invoices.payments.destroy', [$this->invoice, $payment]))->assertRedirect();

    $invoice = $this->invoice->fresh();

    expect($invoice->status)->toBe('sent')
        ->and($invoice->paid_at)->toBeNull()
        ->and($invoice->balance())->toBe(100000);
});

it('reopens as overdue when the due date has passed', function () {
    $this->invoice->update(['due_at' => today()->subDays(5)]);

    ($this->pay)(100000)->assertRedirect();
    $payment = InvoicePayment::first();

    $this->delete(route('invoices.payments.destroy', [$this->invoice, $payment]))->assertRedirect();

    expect($this->invoice->fresh()->status)->toBe('overdue');
});

it('marking paid records a payment for the balance', function () {
    ($this->pay)(30000)->assertRedirect();

    $this->post(route('invoices.paid', $this->invoice))->assertRedirect();

    $invoice = $this->invoice->fresh();

    expect($invoice->status)->toBe('paid')
        ->and($invoice->amountPaid())->toBe(100000)
        ->and($invoice->payments()->count())->toBe(2);
});

it('refuses a payment on a draft or voided invoice', function () {
    $this->invoice->update(['status' => 'draft']);
    ($this->pay)(1000)->assertStatus(422);

    $this->invoice->update(['status' => 'void']);
    ($this->pay)(1000)->assertStatus(422);

    expect(InvoicePayment::count())->toBe(0);
});

it('refuses to void an invoice that has payments recorded', function () {
    ($this->pay)(40000)->assertRedirect();

    $this->post(route('invoices.void', $this->invoice))->assertStatus(422);

    expect($this->invoice->fresh()->status)->toBe('sent');
});

it('allows voiding once the payments are removed', function () {
    ($this->pay)(40000)->assertRedirect();
    $payment = InvoicePayment::first();

    $this->delete(route('invoices.payments.destroy', [$this->invoice, $payment]))->assertRedirect();
    $this->post(route('invoices.void', $this->invoice))->assertRedirect();

    expect($this->invoice->fresh()->status)->toBe('void');
});

it('rejects an unknown payment method', function () {
    $this->post(route('invoices.payments.store', $this->invoice), [
        'amount' => 1000,
        'paid_on' => today()->toDateString(),
        'method' => 'crypto',
    ])->assertSessionHasErrors('method');

    expect(InvoicePayment::count())->toBe(0);
});

it('reports outstanding net of payments on the dashboard', function () {
    ($this->pay)(40000)->assertRedirect();

    $this->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('outstanding', 1)
            ->where('outstanding.0', ['currency' => 'EUR', 'total' => 60000])
        );
});

it('cannot touch payments on another workspace invoice', function () {
    $otherUser = User::factory()->create(['type' => 'freelancer']);
    $otherWorkspace = Workspace::create([
        'name' => 'Other',
        'slug' => 'other-ws-pay',
        'owner_id' => $otherUser->id,
        'currency' => 'EUR',
        'timezone' => 'UTC',
    ]);
    $otherClient = Client::create([
        'workspace_id' => $otherWorkspace->id,
        'name' => 'Globex',
        'currency' => 'EUR',
    ]);
    $otherInvoice = Invoice::create([
        'workspace_id' => $otherWorkspace->id,
        'client_id' => $otherClient->id,
        'created_by' => $otherUser->id,
        'number' => 'INV-2026-8000',
        'status' => 'sent',
        'currency' => 'EUR',
        'subtotal' => 10000,
        'tax_amount' => 0,
        'total' => 10000,
        'tax_rate' => 0,
    ]);

    $this->post(route('invoices.payments.store', $otherInvoice), [
        'amount' => 1000,
        'paid_on' => today()->toDateString(),
        'method' => 'bank',
    ])->assertForbidden();

    expect(InvoicePayment::count())->toBe(0);
});
