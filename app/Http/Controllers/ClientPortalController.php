<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\SendClientPortalAccess;
use App\Concerns\InteractsWithCurrentUser;
use App\Models\Client;
use App\Models\TimeEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ClientPortalController extends Controller
{
    use InteractsWithCurrentUser;

    public function sendAccess(Client $client): RedirectResponse
    {
        abort_unless($client->workspace_id === $this->currentUser()->current_workspace_id, 403);
        abort_unless($client->email !== null, 422, 'Client has no email address.');

        app(SendClientPortalAccess::class)->handle($client);

        return back()->with('success', 'Portal link sent to '.$client->email);
    }

    public function show(string $token): View
    {
        $client = Client::where('portal_token', $token)->firstOrFail();

        $projects = $client->projects()
            ->with([
                'timeEntries' => fn ($q) => $q
                    ->whereNotNull('stopped_at')
                    ->where('billable', true)
                    ->whereDoesntHave('invoices')
                    ->orderBy('started_at'),
            ])
            ->get()
            ->filter(fn ($project) => $project->timeEntries->isNotEmpty());

        return view('portal.approval', [
            'client' => $client,
            'projects' => $projects,
            'token' => $token,
        ]);
    }

    public function approve(string $token): RedirectResponse
    {
        $client = Client::where('portal_token', $token)->firstOrFail();

        $entryIds = $client->projects()
            ->with(['timeEntries' => fn ($q) => $q
                ->whereNotNull('stopped_at')
                ->where('billable', true)
                ->whereDoesntHave('invoices'),
            ])
            ->get()
            ->flatMap(fn ($project) => $project->timeEntries->pluck('id'));

        TimeEntry::whereIn('id', $entryIds)->update(['client_approved' => true]);

        return redirect()->route('client-portal.show', $token)
            ->with('approved', true);
    }
}
