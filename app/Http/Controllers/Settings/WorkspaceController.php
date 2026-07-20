<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Concerns\InteractsWithCurrentUser;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    use InteractsWithCurrentUser;

    public function show(): Response
    {
        return Inertia::render('settings/Workspace', [
            'workspace' => $this->currentUser()->requireCurrentWorkspace(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $workspace = $this->currentUser()->requireCurrentWorkspace();

        abort_unless($workspace->owner_id === Auth::id(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'currency' => ['required', 'string', 'size:3'],
            'timezone' => ['required', 'string', 'timezone:all'],
        ]);

        $workspace->update($data);

        return back()->with('success', 'Workspace updated.');
    }
}
