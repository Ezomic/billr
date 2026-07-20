<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateWorkspace;
use App\Concerns\InteractsWithCurrentUser;
use App\Http\Requests\Workspace\StoreWorkspaceRequest;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    use InteractsWithCurrentUser;

    public function create(): Response
    {
        return Inertia::render('workspaces/Create');
    }

    public function store(StoreWorkspaceRequest $request, CreateWorkspace $action): RedirectResponse
    {
        $action->handle($this->currentUser(), $request->validated('name'));

        return redirect()->route('dashboard')->with('success', 'Workspace created.');
    }

    public function switch(Workspace $workspace): RedirectResponse
    {
        $user = $this->currentUser();

        abort_unless(
            $user->workspaces()->where('workspace_id', $workspace->id)->exists(),
            403,
        );

        $user->update(['current_workspace_id' => $workspace->id]);

        return redirect()->route('dashboard')->with('success', 'Switched to '.$workspace->name.'.');
    }
}
