<?php

use App\Actions\RegisterFreelancer;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Symfony\Component\HttpFoundation\Response;
use Thijssensoftware\IdClient\Exceptions\AccessDeniedException;

function fakeIdUser(): SocialiteUser
{
    return (new SocialiteUser)->setRaw([
        'sub' => '42',
        'name' => 'Robbin Thijssen',
        'email' => 'robbin@example.com',
        'applications' => ['billr'],
    ])->map([
        'id' => '42',
        'name' => 'Robbin Thijssen',
        'email' => 'robbin@example.com',
    ]);
}

function mockSocialite(Closure $configure): void
{
    $provider = Mockery::mock(Provider::class);
    $configure($provider);

    Socialite::shouldReceive('driver')->with('thijssensoftware')->andReturn($provider);
}

/**
 * Signs an existing freelancer in through the ID callback and returns the
 * remember-me cookie the callback set.
 *
 * @return array{string, string}
 */
function signInThroughId(): array
{
    mockSocialite(fn ($provider) => $provider->shouldReceive('user')->andReturn(fakeIdUser()));

    $recaller = Auth::guard()->getRecallerName();
    $cookie = test()->get(route('sso.callback'))->assertRedirect('/dashboard')->getCookie($recaller);

    expect($cookie)->not->toBeNull();

    return [$recaller, (string) $cookie?->getValue()];
}

/**
 * A browser coming back after its session expired, carrying nothing but the
 * remember-me cookie.
 *
 * @return TestResponse<Response>
 */
function returnWithRememberCookie(string $recaller, string $value): TestResponse
{
    Auth::forgetGuards();
    test()->flushSession();

    return test()->withCookie($recaller, $value)->get('/dashboard');
}

it('starts the sso flow from the redirect route', function () {
    mockSocialite(fn ($provider) => $provider->shouldReceive('redirect')->andReturn(redirect('https://id.test/oauth/authorize')));

    $this->get(route('sso.redirect'))->assertRedirect('https://id.test/oauth/authorize');
});

it('links an existing freelancer by email and logs them in', function () {
    $user = User::factory()->create(['email' => 'robbin@example.com', 'type' => 'freelancer', 'idp_id' => null]);

    mockSocialite(fn ($provider) => $provider->shouldReceive('user')->andReturn(fakeIdUser()));

    $this->get(route('sso.callback'))->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user->fresh());
    expect($user->fresh()->idp_id)->toBe('42');
});

it('denies an unknown user because provisioning is disabled', function () {
    mockSocialite(fn ($provider) => $provider->shouldReceive('user')->andReturn(fakeIdUser()));

    $this->get(route('sso.callback'))->assertForbidden();

    $this->assertGuest();
    expect(User::where('email', 'robbin@example.com')->exists())->toBeFalse();
});

it('denies a user without access to billr', function () {
    mockSocialite(fn ($provider) => $provider->shouldReceive('user')->andThrow(new AccessDeniedException('nope')));

    $this->get(route('sso.callback'))->assertForbidden();

    $this->assertGuest();
});

it('keeps a browser signed in through the remember-me cookie after an ID sign-in', function () {
    $user = app(RegisterFreelancer::class)->handle('Robbin Thijssen', 'robbin@example.com', 'a-local-password', 'Thijssen Software');

    [$recaller, $value] = signInThroughId();

    returnWithRememberCookie($recaller, $value)->assertOk();

    $this->assertAuthenticatedAs($user->fresh());
});

it('refuses the remember-me cookie once ID signs the user out', function () {
    config(['id-client.logout_secret' => 'test-logout-secret']);

    app(RegisterFreelancer::class)->handle('Robbin Thijssen', 'robbin@example.com', 'a-local-password', 'Thijssen Software');

    [$recaller, $value] = signInThroughId();

    returnWithRememberCookie($recaller, $value)->assertOk();

    $body = json_encode(['sub' => '42', 'issued_at' => Carbon::now()->getTimestamp()], JSON_THROW_ON_ERROR);

    $this->call('POST', route('sso.logout'), server: [
        'HTTP_X_ID_SIGNATURE' => hash_hmac('sha256', $body, 'test-logout-secret'),
        'CONTENT_TYPE' => 'application/json',
    ], content: $body)->assertOk();

    // Later, so the cookie itself is refused rather than a same-second stamp
    // ending the session it restores.
    $this->travel(1)->minute();

    returnWithRememberCookie($recaller, $value)->assertRedirect(route('login'));

    $this->assertGuest();
});
