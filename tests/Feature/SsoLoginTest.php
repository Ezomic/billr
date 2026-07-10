<?php

use App\Models\User;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
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
