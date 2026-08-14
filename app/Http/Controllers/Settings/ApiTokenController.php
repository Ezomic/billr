<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Concerns\InteractsWithCurrentUser;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Sanctum\PersonalAccessToken;

class ApiTokenController extends Controller
{
    use InteractsWithCurrentUser;

    /** @var list<string> */
    private const ABILITIES = ['time-entries:create'];

    public function show(): Response
    {
        return Inertia::render('settings/ApiTokens', [
            'tokens' => $this->currentUser()->tokens()
                ->orderByDesc('created_at')
                ->get(['id', 'name', 'abilities', 'last_used_at', 'created_at']),
            'abilities' => self::ABILITIES,
            // Only ever present on the redirect straight after creation.
            'newToken' => session('newToken'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => [Rule::in(self::ABILITIES)],
        ]);

        $abilities = $request->collect('abilities')
            ->map(fn (mixed $a): string => is_string($a) ? $a : '')
            ->filter(fn (string $a): bool => in_array($a, self::ABILITIES, true))
            ->values()
            ->all();

        $token = $this->currentUser()->createToken(
            $request->string('name')->toString(),
            $abilities,
        );

        // Flashed rather than returned: the plain text exists exactly once and
        // must not end up in a prop that survives a refresh or a back button.
        return redirect()->route('settings.api-tokens')->with('newToken', $token->plainTextToken);
    }

    public function destroy(PersonalAccessToken $token): RedirectResponse
    {
        abort_unless(
            $token->tokenable_type === $this->currentUser()->getMorphClass()
            && $token->tokenable_id === $this->currentUser()->id,
            403,
        );

        $token->delete();

        return back()->with('success', 'Token revoked.');
    }
}
