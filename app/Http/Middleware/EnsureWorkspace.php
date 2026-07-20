<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorkspace
{
    /**
     * Send freelancers without a current workspace to the creation screen.
     *
     * The access-workspace gate only checks isFreelancer(), and
     * current_workspace_id is nullable, so without this the route runs and
     * fails on a workspace that is not there. Invited users sit in this state
     * between account creation and accepting the invitation.
     *
     * This must not cover workspaces.create/store or workspaces.switch, which
     * are the only ways out of it.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->isFreelancer() && $user->current_workspace_id === null) {
            return redirect()->route('workspaces.create');
        }

        return $next($request);
    }
}
