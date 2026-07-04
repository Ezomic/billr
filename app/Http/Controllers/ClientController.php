<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Client\UpsertClientRequest;
use App\Models\Client;
use App\Models\TimeEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function index(): Response
    {
        $clients = Auth::user()->currentWorkspace->clients()
            ->withCount('projects')
            ->withCount('invoices')
            ->orderBy('name')
            ->get();

        return Inertia::render('clients/Index', [
            'clients' => $clients,
        ]);
    }

    public function show(Client $client): Response
    {
        $this->authorizeClient($client);

        $client->load('projects');

        $pendingEntries = TimeEntry::whereHas('project', fn ($q) => $q->where('client_id', $client->id))
            ->whereNotNull('stopped_at')
            ->where('billable', true)
            ->whereDoesntHave('invoices')
            ->with('project:id,name')
            ->orderBy('started_at')
            ->get();

        return Inertia::render('clients/Show', [
            'client' => $client,
            'pendingEntries' => $pendingEntries,
        ]);
    }

    public function store(UpsertClientRequest $request): RedirectResponse
    {
        Auth::user()->currentWorkspace->clients()->create($request->validated());

        return back()->with('success', 'Client created.');
    }

    public function update(UpsertClientRequest $request, Client $client): RedirectResponse
    {
        $this->authorizeClient($client);

        $client->update($request->validated());

        return back()->with('success', 'Client updated.');
    }

    public function destroy(Client $client): RedirectResponse
    {
        $this->authorizeClient($client);

        $client->delete();

        return back()->with('success', 'Client deleted.');
    }

    private function authorizeClient(Client $client): void
    {
        abort_unless($client->workspace_id === Auth::user()->current_workspace_id, 403);
    }
}
