<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\AcceptClientInvitation;
use App\Actions\AcceptWorkspaceInvitation;
use App\Http\Controllers\Controller;
use App\Models\Invitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends Controller
{
    public function show(string $token): Response|RedirectResponse
    {
        $invitation = Invitation::where('token', $token)->firstOrFail();

        if (! $invitation->isPending()) {
            return redirect()->route('login')->withErrors(['invitation' => 'This invitation is no longer valid.']);
        }

        return Inertia::render('auth/AcceptInvitation', [
            'token' => $token,
            'email' => $invitation->email,
            'type' => $invitation->workspace_id ? 'workspace' : 'client',
            'workspaceName' => $invitation->workspace?->name,
            'clientName' => $invitation->client?->name,
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $invitation = Invitation::where('token', $token)->firstOrFail();

        if (! $invitation->isPending()) {
            return redirect()->route('login')->withErrors(['invitation' => 'This invitation is no longer valid.']);
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($invitation->workspace_id) {
            $user = app(AcceptWorkspaceInvitation::class)->handle(
                invitation: $invitation,
                name: $request->validated('name'),
                password: $request->validated('password'),
            );
            Auth::login($user);
            return redirect()->route('dashboard');
        }

        $user = app(AcceptClientInvitation::class)->handle(
            invitation: $invitation,
            name: $request->validated('name'),
            password: $request->validated('password'),
        );
        Auth::login($user);
        return redirect()->route('portal.dashboard');
    }
}
