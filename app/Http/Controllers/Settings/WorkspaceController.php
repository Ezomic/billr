<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Concerns\InteractsWithCurrentUser;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    use InteractsWithCurrentUser;

    public function show(): Response
    {
        return Inertia::render('settings/Workspace', [
            'workspace' => $this->currentUser()->requireCurrentWorkspace(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $workspace = $this->currentUser()->requireCurrentWorkspace();

        abort_unless($workspace->owner_id === Auth::id(), 403);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'currency' => ['required', 'string', 'size:3'],
            'timezone' => ['required', 'string', 'timezone:all'],
            'payment_terms_days' => ['required', 'integer', 'min:0', 'max:365'],
            'send_payment_reminders' => ['sometimes', 'boolean'],
        ]);

        $workspace->update([
            'name' => $request->string('name')->toString(),
            'currency' => $request->string('currency')->toString(),
            'timezone' => $request->string('timezone')->toString(),
            'payment_terms_days' => $request->integer('payment_terms_days'),
            // Omitting the field leaves the setting alone rather than silently
            // switching reminders off for a caller that never knew about it.
            'send_payment_reminders' => $request->has('send_payment_reminders')
                ? $request->boolean('send_payment_reminders')
                : $workspace->send_payment_reminders,
        ]);

        return back()->with('success', 'Workspace updated.');
    }
}
