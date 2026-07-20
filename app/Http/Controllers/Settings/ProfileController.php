<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Concerns\InteractsWithCurrentUser;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    use InteractsWithCurrentUser;

    public function show(): Response
    {
        return Inertia::render('settings/Profile');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.Auth::id()],
        ]);

        $this->currentUser()->update($data);

        return back()->with('success', 'Profile updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $this->currentUser()->update(['password' => Hash::make($data['password'])]);

        return back()->with('success', 'Password updated.');
    }
}
