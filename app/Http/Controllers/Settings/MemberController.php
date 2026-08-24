<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Concerns\InteractsWithCurrentUser;
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
    use InteractsWithCurrentUser;

    public function show(): Response
    {
        $workspace = $this->currentUser()->requireCurrentWorkspace();

        $members = $workspace->members()
            ->get(['users.id', 'users.name', 'users.email', 'workspace_user.role']);

        $invitations = $workspace->invitations()
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->get(['id', 'email', 'role', 'created_at']);

        return Inertia::render('settings/Members', [
            'members' => $members,
            'invitations' => $invitations,
            'isOwner' => $workspace->owner_id === Auth::id(),
        ]);
    }

    public function invite(Request $request): RedirectResponse
    {
        $workspace = $this->currentUser()->requireCurrentWorkspace();
        abort_unless($workspace->owner_id === Auth::id(), 403);

        $request->validate([
            'email' => ['required', 'email'],
            'role' => ['required', 'in:member'],
        ]);

        Invitation::create([
            'workspace_id' => $workspace->id,
            'email' => $request->string('email')->toString(),
            'role' => $request->string('role')->toString(),
            'token' => Str::random(64),
            'expires_at' => now()->addDays(7),
        ]);

        return back()->with('success', 'Invitation sent.');
    }

    public function remove(User $user): RedirectResponse
    {
        $workspace = $this->currentUser()->requireCurrentWorkspace();
        abort_unless($workspace->owner_id === Auth::id(), 403);
        abort_if($user->id === Auth::id(), 422, 'Cannot remove yourself.');

        $workspace->members()->detach($user->id);

        // Leaving the pointer behind is what let a removed member keep acting in
        // the workspace. Move them to another workspace they are still in, or
        // to nothing, which lands them on the create screen.
        if ($user->current_workspace_id === $workspace->id) {
            $user->forceFill([
                'current_workspace_id' => $user->workspaces()->value('workspaces.id'),
            ])->save();
        }

        return back()->with('success', 'Member removed.');
    }
}
