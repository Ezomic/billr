<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\RegisterFreelancer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RegisterController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('auth/Register');
    }

    public function store(RegisterRequest $request, RegisterFreelancer $action): RedirectResponse
    {
        $user = $action->handle(
            name: $request->validated('name'),
            email: $request->validated('email'),
            password: $request->validated('password'),
            workspaceName: $request->validated('workspace_name'),
        );

        Auth::login($user);

        return redirect()->route('dashboard');
    }
}
