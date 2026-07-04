<?php

namespace Database\Seeders;

use App\Actions\CreateInvoiceFromTimeEntries;
use App\Actions\CreateWorkspace;
use App\Models\Client;
use App\Models\Project;
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
            'name'     => 'Dev User',
            'email'    => 'dev@billr.test',
            'password' => Hash::make('password'),
            'type'     => 'freelancer',
        ]);

        $workspace = app(CreateWorkspace::class)->handle($user, 'Dev Studio');

        // Clients
        $acme = Client::create([
            'workspace_id' => $workspace->id,
            'name'         => 'Acme Corp',
            'email'        => 'finance@acme.example',
            'city'         => 'Amsterdam',
            'country'      => 'NL',
            'vat_number'   => 'NL123456789B01',
            'currency'     => 'EUR',
        ]);

        $globalTech = Client::create([
            'workspace_id' => $workspace->id,
            'name'         => 'GlobalTech Ltd',
            'email'        => 'accounts@globaltech.example',
            'city'         => 'London',
            'country'      => 'GB',
            'currency'     => 'GBP',
        ]);

        $startupCo = Client::create([
            'workspace_id' => $workspace->id,
            'name'         => 'StartupCo',
            'email'        => 'hello@startupco.example',
            'city'         => 'Berlin',
            'country'      => 'DE',
            'currency'     => 'EUR',
        ]);

        // Projects
        $acmeWeb = Project::create([
            'workspace_id' => $workspace->id,
            'client_id'    => $acme->id,
            'name'         => 'Website Redesign',
            'type'         => 'hourly',
            'hourly_rate'  => 9500, // €95/hr
            'status'       => 'active',
        ]);

        $acmeMaint = Project::create([
            'workspace_id' => $workspace->id,
            'client_id'    => $acme->id,
            'name'         => 'Monthly Maintenance',
            'type'         => 'fixed',
            'fixed_price'  => 120000, // €1200/month
            'status'       => 'active',
        ]);

        $globalApi = Project::create([
            'workspace_id' => $workspace->id,
            'client_id'    => $globalTech->id,
            'name'         => 'API Integration',
            'type'         => 'hourly',
            'hourly_rate'  => 11000, // £110/hr
            'status'       => 'active',
        ]);

        $startupMvp = Project::create([
            'workspace_id' => $workspace->id,
            'client_id'    => $startupCo->id,
            'name'         => 'MVP Build',
            'type'         => 'hourly',
            'hourly_rate'  => 8500, // €85/hr
            'status'       => 'active',
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
            $stop  = $start->addMinutes($minutes);

            $timeEntryModels[] = TimeEntry::create([
                'project_id'       => $project->id,
                'user_id'          => $user->id,
                'description'      => $description,
                'started_at'       => $start,
                'stopped_at'       => $stop,
                'duration_minutes' => $minutes,
                'hourly_rate'      => $project->hourly_rate,
                'billable'         => true,
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
            'status'    => 'paid',
            'issued_at' => now()->subDays(30),
            'due_at'    => now()->subDays(0),
            'paid_at'   => now()->subDays(5),
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
            'status'    => 'sent',
            'issued_at' => now()->subDays(12),
            'due_at'    => now()->addDays(18),
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

        // Client portal user for Acme
        $clientUser = User::create([
            'name'     => 'Acme Finance',
            'email'    => 'portal@acme.example',
            'password' => Hash::make('password'),
            'type'     => 'client',
        ]);
        $acme->portalUsers()->attach($clientUser->id);
    }
}
