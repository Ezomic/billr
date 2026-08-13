<?php

declare(strict_types=1);

use App\Models\Client;
use App\Models\Invoice;
use App\Models\RecurringInvoice;
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

    $this->client = Client::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Acme',
        'currency' => 'USD',
    ]);

    $this->makeSchedule = function (array $overrides = [], array $lines = [['Retainer', 1, 200000]]): RecurringInvoice {
        $schedule = $this->workspace->recurringInvoices()->create(array_merge([
            'client_id' => $this->client->id,
            'created_by' => $this->user->id,
            'name' => 'Monthly retainer',
            'interval' => 'monthly',
            'start_on' => today(),
            'next_run_on' => today(),
            'currency' => 'USD',
            'tax_rate' => 21,
            'notes' => 'Thanks',
            'status' => 'active',
        ], $overrides));

        $sort = 0;
        foreach ($lines as [$description, $qty, $price]) {
            $schedule->lines()->create([
                'description' => $description,
                'quantity' => $qty,
                'unit' => 'fixed',
                'unit_price' => $price,
                'amount' => $qty * $price,
                'sort_order' => $sort++,
            ]);
        }

        return $schedule;
    };
});

it('generates a draft invoice from a due schedule', function () {
    $schedule = ($this->makeSchedule)();

    $this->artisan('invoices:generate-recurring')->assertSuccessful();

    $invoice = Invoice::with('lines')->first();

    expect($invoice)->not->toBeNull()
        ->and($invoice->status)->toBe('draft')
        ->and($invoice->client_id)->toBe($this->client->id)
        ->and($invoice->recurring_invoice_id)->toBe($schedule->id)
        ->and($invoice->notes)->toBe('Thanks')
        ->and($invoice->lines)->toHaveCount(1)
        ->and($invoice->subtotal)->toBe(200000)
        ->and($invoice->tax_amount)->toBe(42000)
        ->and($invoice->total)->toBe(242000);
});

it('advances the schedule after generating', function () {
    $schedule = ($this->makeSchedule)(['next_run_on' => today()]);

    $this->artisan('invoices:generate-recurring')->assertSuccessful();

    expect($schedule->fresh()->next_run_on->toDateString())
        ->toBe(today()->addMonth()->toDateString());
});

it('does not generate the same period twice', function () {
    $schedule = ($this->makeSchedule)();

    $this->artisan('invoices:generate-recurring')->assertSuccessful();
    $this->artisan('invoices:generate-recurring')->assertSuccessful();

    expect(Invoice::count())->toBe(1);

    // Rewind the schedule and run again: the unique index must still hold.
    $schedule->update(['next_run_on' => today()]);
    $this->artisan('invoices:generate-recurring')->assertSuccessful();

    expect(Invoice::count())->toBe(1);
});

it('does not fire once per missed period on a catch-up run', function () {
    ($this->makeSchedule)([
        'start_on' => today()->subMonths(6),
        'next_run_on' => today()->subMonths(6),
    ]);

    $this->artisan('invoices:generate-recurring')->assertSuccessful();

    expect(Invoice::count())->toBe(1);
});

it('skips a paused schedule', function () {
    ($this->makeSchedule)(['status' => 'paused']);

    $this->artisan('invoices:generate-recurring')->assertSuccessful();

    expect(Invoice::count())->toBe(0);
});

it('skips a schedule that has not come due', function () {
    ($this->makeSchedule)(['next_run_on' => today()->addDay()]);

    $this->artisan('invoices:generate-recurring')->assertSuccessful();

    expect(Invoice::count())->toBe(0);
});

it('skips a schedule past its end date', function () {
    ($this->makeSchedule)([
        'start_on' => today()->subMonths(3),
        'next_run_on' => today(),
        'end_on' => today()->subDay(),
    ]);

    $this->artisan('invoices:generate-recurring')->assertSuccessful();

    expect(Invoice::count())->toBe(0);
});

it('skips a schedule with no lines', function () {
    ($this->makeSchedule)([], []);

    $this->artisan('invoices:generate-recurring')->assertSuccessful();

    expect(Invoice::count())->toBe(0);
});

it('generates nothing on a dry run', function () {
    $schedule = ($this->makeSchedule)();

    $this->artisan('invoices:generate-recurring', ['--dry-run' => true])->assertSuccessful();

    expect(Invoice::count())->toBe(0)
        ->and($schedule->fresh()->next_run_on->toDateString())->toBe(today()->toDateString());
});

it('allocates gap-free numbers alongside manually created invoices', function () {
    ($this->makeSchedule)();

    Invoice::create([
        'workspace_id' => $this->workspace->id,
        'client_id' => $this->client->id,
        'created_by' => $this->user->id,
        'number' => 'INV-'.now()->year.'-0001',
        'status' => 'draft',
        'currency' => 'USD',
        'subtotal' => 0,
        'tax_amount' => 0,
        'total' => 0,
        'tax_rate' => 0,
    ]);

    $this->artisan('invoices:generate-recurring')->assertSuccessful();

    $generated = Invoice::whereNotNull('recurring_invoice_id')->first();

    expect($generated->number)->toBe('INV-'.now()->year.'-0002');
});

it('honours the client payment terms on the generated invoice', function () {
    $this->client->update(['payment_terms_days' => 7]);
    ($this->makeSchedule)(['next_run_on' => today()]);

    $this->artisan('invoices:generate-recurring')->assertSuccessful();

    $invoice = Invoice::first();

    expect($invoice->issued_at->toDateString())->toBe(today()->toDateString())
        ->and($invoice->due_at->toDateString())->toBe(today()->addDays(7)->toDateString());
});

it('keeps generating on the following period', function () {
    $schedule = ($this->makeSchedule)();

    $this->artisan('invoices:generate-recurring')->assertSuccessful();

    // Pretend a month has passed by pulling the next run back to today.
    $schedule->update(['next_run_on' => today()->addMonth()]);
    $this->travelTo(today()->addMonth());

    $this->artisan('invoices:generate-recurring')->assertSuccessful();

    expect(Invoice::count())->toBe(2)
        ->and(Invoice::pluck('recurring_period')->unique())->toHaveCount(2);
});
