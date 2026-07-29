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
            name: $request->string('name')->toString(),
            email: $request->string('email')->toString(),
            password: $request->string('password')->toString(),
            workspaceName: $request->string('workspace_name')->toString(),
        );

        Auth::login($user);

        return redirect()->route('dashboard');
    }
}
