<?php

namespace Database\Seeders;

use App\Actions\CreateInvoiceFromTimeEntries;
use App\Actions\CreateWorkspace;
use App\Models\Client;
use App\Models\Project;
use App\Models\RecurringInvoice;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Dev freelancer account
        $user = User::create([
            'name' => 'Dev User',
            'email' => 'dev@billr.test',
            'password' => Hash::make('password'),
            'type' => 'freelancer',
        ]);

        $workspace = app(CreateWorkspace::class)->handle($user, 'Dev Studio');
        $workspace->update([
            'payment_terms_days' => 21,
            'send_payment_reminders' => true,
        ]);

        // Clients
        $acme = Client::create([
            'workspace_id' => $workspace->id,
            'name' => 'Acme Corp',
            'email' => 'finance@acme.example',
            'city' => 'Amsterdam',
            'country' => 'NL',
            'vat_number' => 'NL123456789B01',
            'currency' => 'EUR',
            'payment_terms_days' => 7, // overrides the workspace default
        ]);

        $globalTech = Client::create([
            'workspace_id' => $workspace->id,
            'name' => 'GlobalTech Ltd',
            'email' => 'accounts@globaltech.example',
            'city' => 'London',
            'country' => 'GB',
            'currency' => 'GBP',
        ]);

        $startupCo = Client::create([
            'workspace_id' => $workspace->id,
            'name' => 'StartupCo',
            'email' => 'hello@startupco.example',
            'city' => 'Berlin',
            'country' => 'DE',
            'currency' => 'EUR',
        ]);

        // Projects
        $acmeWeb = Project::create([
            'workspace_id' => $workspace->id,
            'client_id' => $acme->id,
            'name' => 'Website Redesign',
            'type' => 'hourly',
            'hourly_rate' => 9500, // €95/hr
            'status' => 'active',
        ]);

        $acmeMaint = Project::create([
            'workspace_id' => $workspace->id,
            'client_id' => $acme->id,
            'name' => 'Monthly Maintenance',
            'type' => 'fixed',
            'fixed_price' => 120000, // €1200/month
            'status' => 'active',
        ]);

        $globalApi = Project::create([
            'workspace_id' => $workspace->id,
            'client_id' => $globalTech->id,
            'name' => 'API Integration',
            'type' => 'hourly',
            'hourly_rate' => 11000, // £110/hr
            'status' => 'active',
        ]);

        $acmeArchived = Project::create([
            'workspace_id' => $workspace->id,
            'client_id' => $acme->id,
            'name' => 'Brand Refresh (finished)',
            'type' => 'fixed',
            'fixed_price' => 450000,
            'status' => 'archived',
        ]);

        $startupMvp = Project::create([
            'workspace_id' => $workspace->id,
            'client_id' => $startupCo->id,
            'name' => 'MVP Build',
            'type' => 'hourly',
            'hourly_rate' => 8500, // €85/hr
            'status' => 'active',
        ]);

        // Time entries — spread over last 60 days
        $entries = [
            // Acme — Website Redesign (older, already invoiced)
            [$acmeWeb, 'Discovery & wireframes', 150, 60],
            [$acmeWeb, 'Homepage design', 135, 57],
            [$acmeWeb, 'Component library', 240, 50],
            [$acmeWeb, 'Frontend implementation', 300, 44],
            [$acmeWeb, 'CMS integration', 195, 38],
            // Acme — recent (unbilled)
            [$acmeWeb, 'SEO optimisation', 120, 10],
            [$acmeWeb, 'Performance audit', 90, 5],
            // GlobalTech — API Integration
            [$globalApi, 'Requirements gathering', 90, 45],
            [$globalApi, 'Auth flow implementation', 180, 40],
            [$globalApi, 'Webhook endpoints', 150, 33],
            [$globalApi, 'Testing & documentation', 120, 25],
            [$globalApi, 'Bug fixes after UAT', 60, 15],
            // StartupCo — MVP
            [$startupMvp, 'Project setup & architecture', 120, 20],
            [$startupMvp, 'User authentication', 150, 16],
            [$startupMvp, 'Dashboard UI', 180, 12],
            [$startupMvp, 'API endpoints', 210, 8],
            [$startupMvp, 'Testing', 90, 3],
        ];

        $timeEntryModels = [];
        foreach ($entries as [$project, $description, $minutes, $daysAgo]) {
            $start = CarbonImmutable::now()->subDays($daysAgo)->setTime(9, 0);
            $stop = $start->addMinutes($minutes);

            $timeEntryModels[] = TimeEntry::create([
                'project_id' => $project->id,
                'user_id' => $user->id,
                'description' => $description,
                'started_at' => $start,
                'stopped_at' => $stop,
                'duration_minutes' => $minutes,
                'hourly_rate' => $project->hourly_rate,
                'billable' => true,
            ]);
        }

        // Invoice 1 — Acme, paid (older entries)
        $acmeEntries = collect($timeEntryModels)
            ->filter(fn ($e) => in_array($e->description, [
                'Discovery & wireframes', 'Homepage design', 'Component library',
                'Frontend implementation', 'CMS integration',
            ]))
            ->pluck('id');

        $invoice1 = app(CreateInvoiceFromTimeEntries::class)->handle(
            user: $user,
            client: $acme,
            timeEntryIds: $acmeEntries,
            taxRate: 21,
        );
        $invoice1->update([
            'status' => 'paid',
            'issued_at' => now()->subDays(30),
            'due_at' => now()->subDays(0),
            'paid_at' => now()->subDays(5),
        ]);

        // Invoice 2 — GlobalTech, sent
        $globalEntries = collect($timeEntryModels)
            ->filter(fn ($e) => $e->project_id === $globalApi->id)
            ->pluck('id');

        $invoice2 = app(CreateInvoiceFromTimeEntries::class)->handle(
            user: $user,
            client: $globalTech,
            timeEntryIds: $globalEntries,
            taxRate: 0,
        );
        $invoice2->update([
            'status' => 'sent',
            'issued_at' => now()->subDays(12),
            'due_at' => now()->addDays(18),
        ]);

        // Invoice 3 — StartupCo, draft
        $startupEntries = collect($timeEntryModels)
            ->filter(fn ($e) => $e->project_id === $startupMvp->id)
            ->pluck('id');

        app(CreateInvoiceFromTimeEntries::class)->handle(
            user: $user,
            client: $startupCo,
            timeEntryIds: $startupEntries,
            taxRate: 19,
        );

        // Invoice 4 — Acme, overdue and old enough for the 7-day reminder
        // milestone, so invoices:send-reminders has something to act on.
        $overdueEntry = TimeEntry::create([
            'project_id' => $acmeWeb->id,
            'user_id' => $user->id,
            'description' => 'Accessibility pass',
            'started_at' => CarbonImmutable::now()->subDays(70)->setTime(9, 0),
            'stopped_at' => CarbonImmutable::now()->subDays(70)->setTime(13, 0),
            'duration_minutes' => 240,
            'hourly_rate' => $acmeWeb->hourly_rate,
            'billable' => true,
        ]);

        $invoice4 = app(CreateInvoiceFromTimeEntries::class)->handle(
            user: $user,
            client: $acme,
            timeEntryIds: collect([$overdueEntry->id]),
            taxRate: 21,
        );
        $invoice4->update([
            'status' => 'overdue',
            'issued_at' => now()->subDays(40),
            'due_at' => now()->subDays(9),
        ]);

        // Invoice 5 — GlobalTech, part paid, so the balance and the derived
        // status have something non-trivial to show.
        $partPaidEntry = TimeEntry::create([
            'project_id' => $globalApi->id,
            'user_id' => $user->id,
            'description' => 'Rate limiting',
            'started_at' => CarbonImmutable::now()->subDays(22)->setTime(9, 0),
            'stopped_at' => CarbonImmutable::now()->subDays(22)->setTime(12, 0),
            'duration_minutes' => 180,
            'hourly_rate' => $globalApi->hourly_rate,
            'billable' => true,
        ]);

        $invoice5 = app(CreateInvoiceFromTimeEntries::class)->handle(
            user: $user,
            client: $globalTech,
            timeEntryIds: collect([$partPaidEntry->id]),
            taxRate: 0,
        );
        $invoice5->update([
            'status' => 'sent',
            'issued_at' => now()->subDays(20),
            'due_at' => now()->addDays(1),
        ]);
        $invoice5->payments()->create([
            'recorded_by' => $user->id,
            'amount' => (int) round($invoice5->total / 3),
            'paid_on' => now()->subDays(4),
            'method' => 'bank',
            'note' => 'First instalment',
        ]);
        $invoice5->refresh()->syncStatusWithBalance();

        // Invoice 6 — StartupCo, voided, so the terminal state is represented
        $voidEntry = TimeEntry::create([
            'project_id' => $startupMvp->id,
            'user_id' => $user->id,
            'description' => 'Scope later cancelled',
            'started_at' => CarbonImmutable::now()->subDays(26)->setTime(9, 0),
            'stopped_at' => CarbonImmutable::now()->subDays(26)->setTime(11, 0),
            'duration_minutes' => 120,
            'hourly_rate' => $startupMvp->hourly_rate,
            'billable' => true,
        ]);

        $invoice6 = app(CreateInvoiceFromTimeEntries::class)->handle(
            user: $user,
            client: $startupCo,
            timeEntryIds: collect([$voidEntry->id]),
            taxRate: 19,
        );
        $invoice6->timeEntries()->detach();
        $invoice6->update(['status' => 'void']);

        // Recurring schedule, due today so invoices:generate-recurring has work
        $retainer = RecurringInvoice::create([
            'workspace_id' => $workspace->id,
            'client_id' => $acme->id,
            'created_by' => $user->id,
            'name' => 'Acme monthly retainer',
            'interval' => 'monthly',
            'start_on' => now()->startOfMonth(),
            'next_run_on' => today(),
            'currency' => 'EUR',
            'tax_rate' => 21,
            'notes' => 'Retainer covering support and small changes.',
            'status' => 'active',
        ]);
        $retainer->lines()->create([
            'description' => 'Monthly retainer',
            'quantity' => 1,
            'unit' => 'fixed',
            'unit_price' => 150000,
            'amount' => 150000,
            'sort_order' => 0,
        ]);

        // A paused schedule, so both states are visible
        $paused = RecurringInvoice::create([
            'workspace_id' => $workspace->id,
            'client_id' => $startupCo->id,
            'created_by' => $user->id,
            'name' => 'StartupCo hosting (paused)',
            'interval' => 'quarterly',
            'start_on' => now()->subMonths(3)->startOfMonth(),
            'next_run_on' => today()->addMonths(2),
            'currency' => 'EUR',
            'tax_rate' => 19,
            'status' => 'paused',
        ]);
        $paused->lines()->create([
            'description' => 'Hosting and monitoring',
            'quantity' => 1,
            'unit' => 'fixed',
            'unit_price' => 45000,
            'amount' => 45000,
            'sort_order' => 0,
        ]);

        // A second member, so the team-time view and invitations have subjects
        $member = User::create([
            'name' => 'Sam Contractor',
            'email' => 'sam@billr.test',
            'password' => Hash::make('password'),
            'type' => 'freelancer',
            'current_workspace_id' => $workspace->id,
        ]);
        $workspace->members()->attach($member->id, ['role' => 'member']);

        TimeEntry::create([
            'project_id' => $startupMvp->id,
            'user_id' => $member->id,
            'description' => 'Pair session on the API',
            'started_at' => CarbonImmutable::now()->subDays(2)->setTime(10, 0),
            'stopped_at' => CarbonImmutable::now()->subDays(2)->setTime(13, 0),
            'duration_minutes' => 180,
            'hourly_rate' => $startupMvp->hourly_rate,
            'billable' => true,
        ]);

        // An API token, so the ingest endpoint can be exercised without setup
        $user->createToken('Seeded dev token', ['time-entries:create']);

        // Client portal user for Acme
        $clientUser = User::create([
            'name' => 'Acme Finance',
            'email' => 'portal@acme.example',
            'password' => Hash::make('password'),
            'type' => 'client',
        ]);
        $acme->portalUsers()->attach($clientUser->id);

        // Portal token for the tokened timesheet approval page
        $acme->forceFill(['portal_token' => 'demo-portal-token'])->save();
    }
}
