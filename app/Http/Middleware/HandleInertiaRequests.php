<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Portal\IdPortalClient;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'type' => $user->type,
                ] : null,
                'workspace' => $user?->isFreelancer() ? $user->usableCurrentWorkspace() : null,
                'workspaces' => $user?->isFreelancer()
                    ? $user->workspaces()->orderBy('name')->get(['workspaces.id', 'workspaces.name', 'workspaces.slug'])
                    : [],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'portalApps' => fn () => $user === null
                ? []
                : app(IdPortalClient::class)->appsFor($user)['apps'],
            'portalCategories' => fn () => $user === null
                ? []
                : app(IdPortalClient::class)->appsFor($user)['categories'],
        ];
    }
}
