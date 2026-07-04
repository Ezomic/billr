<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    public function show(): Response
    {
        $workspace = Auth::user()->currentWorkspace;

        $members = $workspace->members()
            ->get(['users.id', 'users.name', 'users.email', 'workspace_user.role']);

        $invitations = $workspace->invitations()
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->get(['id', 'email', 'role', 'created_at']);

        return Inertia::render('settings/Members', [
            'members'     => $members,
            'invitations' => $invitations,
            'isOwner'     => $workspace->owner_id === Auth::id(),
        ]);
    }

    public function invite(Request $request): RedirectResponse
    {
        $workspace = Auth::user()->currentWorkspace;
        abort_unless($workspace->owner_id === Auth::id(), 403);

        $data = $request->validate([
            'email' => ['required', 'email'],
            'role'  => ['required', 'in:member'],
        ]);

        Invitation::create([
            'workspace_id' => $workspace->id,
            'email'        => $data['email'],
            'role'         => $data['role'],
            'token'        => Str::random(64),
            'expires_at'   => now()->addDays(7),
        ]);

        return back()->with('success', 'Invitation sent.');
    }

    public function remove(User $user): RedirectResponse
    {
        $workspace = Auth::user()->currentWorkspace;
        abort_unless($workspace->owner_id === Auth::id(), 403);
        abort_if($user->id === Auth::id(), 422, 'Cannot remove yourself.');

        $workspace->members()->detach($user->id);

        return back()->with('success', 'Member removed.');
    }
}
