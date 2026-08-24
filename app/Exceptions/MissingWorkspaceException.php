<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\User;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class MissingWorkspaceException extends RuntimeException
{
    public function __construct(User $user)
    {
        parent::__construct("User {$user->id} has no usable current workspace.");
    }

    /**
     * The web routes are already redirected by EnsureWorkspace, so reaching here
     * means a path that skips it, chiefly the API. Answer with a refusal rather
     * than a 500: the caller is authenticated, they just have no workspace to
     * act in, which is a permission answer and not a server fault.
     */
    public function render(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'You are not a member of a workspace.',
            ], 403);
        }

        return redirect()->route('workspaces.create');
    }
}
