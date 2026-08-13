<?php

declare(strict_types=1);

use App\Mail\InvoiceReminderMail;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceReminder;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();

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
        'email' => 'billing@acme.example',
        'currency' => 'USD',
    ]);

    $this->overdueBy = function (int $days, string $status = 'overdue', ?string $email = 'billing@acme.example'): Invoice {
        static $n = 0;
        $n++;

        $client = Client::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Client '.$n,
            'email' => $email,
            'currency' => 'USD',
        ]);

        return Invoice::create([
            'workspace_id' => $this->workspace->id,
            'client_id' => $client->id,
            'created_by' => $this->user->id,
            'number' => 'INV-2026-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT),
            'status' => $status,
            'currency' => 'USD',
            'subtotal' => 10000,
            'tax_amount' => 0,
            'total' => 10000,
            'tax_rate' => 0,
            'issued_at' => today()->subDays($days + 30),
            'due_at' => today()->subDays($days),
        ]);
    };
});

it('sends a reminder once an invoice is three days overdue', function () {
    $invoice = ($this->overdueBy)(3);

    $this->artisan('invoices:send-reminders')->assertSuccessful();

    Mail::assertSent(InvoiceReminderMail::class, fn ($mail) => $mail->invoice->is($invoice) && $mail->daysOverdue === 3);

    expect($invoice->reminders()->count())->toBe(1);
});

it('does not remind before the first milestone', function () {
    ($this->overdueBy)(1);

    $this->artisan('invoices:send-reminders')->assertSuccessful();

    Mail::assertNothingSent();
});

it('does not send the same milestone twice', function () {
    $invoice = ($this->overdueBy)(3);

    $this->artisan('invoices:send-reminders')->assertSuccessful();
    $this->artisan('invoices:send-reminders')->assertSuccessful();

    Mail::assertSentCount(1);
    expect($invoice->reminders()->count())->toBe(1);
});

it('sends the next milestone as the invoice ages', function () {
    $invoice = ($this->overdueBy)(3);

    $this->artisan('invoices:send-reminders')->assertSuccessful();

    $invoice->update(['due_at' => today()->subDays(7)]);

    $this->artisan('invoices:send-reminders')->assertSuccessful();

    Mail::assertSentCount(2);
    expect($invoice->reminders()->pluck('days_overdue')->sort()->values()->all())->toBe([3, 7]);
});

it('sends only the highest passed milestone when it has never reminded', function () {
    $invoice = ($this->overdueBy)(30);

    $this->artisan('invoices:send-reminders')->assertSuccessful();

    Mail::assertSentCount(1);
    Mail::assertSent(InvoiceReminderMail::class, fn ($mail) => $mail->daysOverdue === 14);
    expect($invoice->reminders()->count())->toBe(1);
});

it('never reminds on a paid or voided invoice', function () {
    ($this->overdueBy)(10, 'paid');
    ($this->overdueBy)(10, 'void');
    ($this->overdueBy)(10, 'draft');

    $this->artisan('invoices:send-reminders')->assertSuccessful();

    Mail::assertNothingSent();
});

it('skips a client with no email address', function () {
    $invoice = ($this->overdueBy)(10, 'overdue', null);

    $this->artisan('invoices:send-reminders')->assertSuccessful();

    Mail::assertNothingSent();
    expect($invoice->reminders()->count())->toBe(0);
});

it('respects the workspace reminder switch', function () {
    $this->workspace->update(['send_payment_reminders' => false]);

    ($this->overdueBy)(10);

    $this->artisan('invoices:send-reminders')->assertSuccessful();

    Mail::assertNothingSent();
});

it('does not record a reminder when sending fails', function () {
    $invoice = ($this->overdueBy)(3);

    Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP down'));

    $this->artisan('invoices:send-reminders')->assertSuccessful();

    expect($invoice->reminders()->count())->toBe(0);
});

it('sends nothing on a dry run', function () {
    $invoice = ($this->overdueBy)(3);

    $this->artisan('invoices:send-reminders', ['--dry-run' => true])->assertSuccessful();

    Mail::assertNothingSent();
    expect($invoice->reminders()->count())->toBe(0);
});

it('keeps the unique claim per invoice and milestone', function () {
    $invoice = ($this->overdueBy)(3);

    InvoiceReminder::create([
        'invoice_id' => $invoice->id,
        'days_overdue' => 3,
        'sent_to' => 'billing@acme.example',
        'sent_at' => now(),
    ]);

    expect(fn () => InvoiceReminder::create([
        'invoice_id' => $invoice->id,
        'days_overdue' => 3,
        'sent_to' => 'billing@acme.example',
        'sent_at' => now(),
    ]))->toThrow(QueryException::class);
});
