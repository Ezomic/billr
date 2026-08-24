<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    RateLimiter::clear('');

    $this->user = User::factory()->create([
        'type' => 'freelancer',
        'email' => 'freelancer@example.com',
        'password' => Hash::make('correct-horse'),
    ]);

    $this->attempt = fn (string $password, string $email = 'freelancer@example.com') => test()->post(route('login'), [
        'email' => $email,
        'password' => $password,
    ]);
});

it('locks out after five wrong passwords', function () {
    foreach (range(1, 5) as $ignored) {
        ($this->attempt)('wrong')->assertSessionHasErrors('email');
    }

    ($this->attempt)('wrong')
        ->assertSessionHasErrors('email');

    expect(session('errors')->first('email'))->toContain('Too many login attempts');
});

it('refuses the correct password while locked out', function () {
    foreach (range(1, 6) as $ignored) {
        ($this->attempt)('wrong');
    }

    ($this->attempt)('correct-horse')->assertSessionHasErrors('email');

    expect(auth()->check())->toBeFalse();
});

it('still lets a valid login through under the limit', function () {
    ($this->attempt)('wrong')->assertSessionHasErrors('email');

    ($this->attempt)('correct-horse')->assertRedirect();

    expect(auth()->check())->toBeTrue();
});

it('clears the counter after a successful login', function () {
    foreach (range(1, 4) as $ignored) {
        ($this->attempt)('wrong');
    }

    ($this->attempt)('correct-horse')->assertRedirect();

    auth()->logout();
    session()->flush();

    // The counter was reset, so a fresh run of wrong attempts is allowed again
    // rather than tripping immediately on the old tally.
    ($this->attempt)('wrong')->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->not->toContain('Too many login attempts');
});

it('does not let one attacker lock out a user from a different address', function () {
    // Same email, different IP: the key includes the IP, so this must not
    // consume the victim's own allowance.
    foreach (range(1, 6) as $ignored) {
        $this->call('POST', route('login'), [
            'email' => 'freelancer@example.com',
            'password' => 'wrong',
        ], server: ['REMOTE_ADDR' => '203.0.113.9']);
    }

    $this->call('POST', route('login'), [
        'email' => 'freelancer@example.com',
        'password' => 'correct-horse',
    ], server: ['REMOTE_ADDR' => '198.51.100.4'])->assertRedirect();

    expect(auth()->check())->toBeTrue();
});

it('counts each email separately from the same address', function () {
    foreach (range(1, 6) as $ignored) {
        ($this->attempt)('wrong', 'someone-else@example.com');
    }

    ($this->attempt)('correct-horse')->assertRedirect();

    expect(auth()->check())->toBeTrue();
});
