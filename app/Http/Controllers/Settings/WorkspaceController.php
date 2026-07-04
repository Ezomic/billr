<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('settings/Workspace', [
            'workspace' => Auth::user()->currentWorkspace,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $workspace = Auth::user()->currentWorkspace;

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
