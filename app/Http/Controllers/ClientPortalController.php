<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\SendClientPortalAccess;
use App\Concerns\InteractsWithCurrentUser;
use App\Models\Client;
use App\Models\TimeEntry;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
                'timeEntries' => fn (Relation $q) => $q
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

    public function approve(Request $request, string $token): RedirectResponse
    {
        $client = Client::where('portal_token', $token)->firstOrFail();

        $request->validate([
            'time_entry_ids' => ['required', 'array', 'min:1'],
            'time_entry_ids.*' => ['integer'],
        ]);

        // Intersecting against the client's own pending entries keeps a submitted
        // id from reaching another client's timesheet, and drops anything that was
        // billed or invoiced between the page rendering and this submission.
        $ids = $request->collect('time_entry_ids')
            ->map(fn (mixed $id): int => is_numeric($id) ? (int) $id : 0)
            ->intersect($this->approvableEntryIds($client))
            ->values();

        if ($ids->isEmpty()) {
            return back()->withErrors([
                'time_entry_ids' => 'Those time entries are no longer awaiting your approval.',
            ]);
        }

        TimeEntry::whereIn('id', $ids)->update(['client_approved' => true]);

        return redirect()->route('client-portal.show', $token)
            ->with('approved', $ids->count());
    }

    /** @return Collection<int, int> */
    private function approvableEntryIds(Client $client): Collection
    {
        return TimeEntry::query()
            ->whereIn('project_id', $client->projects()->select('projects.id'))
            ->whereNotNull('stopped_at')
            ->where('billable', true)
            ->whereDoesntHave('invoices')
            ->pluck('id')
            ->map(fn (mixed $id): int => is_numeric($id) ? (int) $id : 0)
            ->values();
    }
}
