<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Workspace;
use Laravel\Sanctum\PersonalAccessToken;

beforeEach(function () {
    $this->user = User::factory()->create(['type' => 'freelancer']);
    $this->workspace = Workspace::create([
        'name' => 'Test Workspace',
        'slug' => 'test-workspace',
        'owner_id' => $this->user->id,
        'currency' => 'USD',
        'timezone' => 'UTC',
    ]);
    $this->workspace->members()->attach($this->user->id, ['role' => 'owner']);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);

    $this->actingAs($this->user);
});

it('lists the current user tokens', function () {
    $this->user->createToken('Chronos sync', ['time-entries:create']);

    $this->get(route('settings.api-tokens'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/ApiTokens')
            ->has('tokens', 1)
            ->where('tokens.0.name', 'Chronos sync')
            ->where('newToken', null)
        );
});

it('creates a token and shows the plain text exactly once', function () {
    $this->post(route('settings.api-tokens.store'), [
        'name' => 'Chronos sync',
        'abilities' => ['time-entries:create'],
    ])->assertRedirect();

    expect(PersonalAccessToken::count())->toBe(1);

    // The redirect target carries the plain text...
    $this->followingRedirects()
        ->post(route('settings.api-tokens.store'), [
            'name' => 'Second',
            'abilities' => ['time-entries:create'],
        ])
        ->assertInertia(fn ($page) => $page->where('newToken', fn ($t) => is_string($t) && $t !== ''));

    // ...and a plain reload does not.
    $this->get(route('settings.api-tokens'))
        ->assertInertia(fn ($page) => $page->where('newToken', null));
});

it('never stores the token in plain text', function () {
    $this->post(route('settings.api-tokens.store'), [
        'name' => 'Chronos sync',
        'abilities' => ['time-entries:create'],
    ])->assertRedirect();

    $plain = session('newToken');
    $stored = PersonalAccessToken::first();

    expect($plain)->toBeString()
        ->and($stored->token)->not->toBe($plain)
        ->and($stored->token)->toBe(hash('sha256', explode('|', (string) $plain)[1]));
});

it('rejects an unknown ability', function () {
    $this->post(route('settings.api-tokens.store'), [
        'name' => 'Sneaky',
        'abilities' => ['invoices:delete'],
    ])->assertSessionHasErrors('abilities.0');

    expect(PersonalAccessToken::count())->toBe(0);
});

it('requires a name and at least one ability', function () {
    $this->post(route('settings.api-tokens.store'), ['name' => '', 'abilities' => []])
        ->assertSessionHasErrors(['name', 'abilities']);

    expect(PersonalAccessToken::count())->toBe(0);
});

it('issues a token that resolves to its owner with the granted ability', function () {
    $this->post(route('settings.api-tokens.store'), [
        'name' => 'Chronos sync',
        'abilities' => ['time-entries:create'],
    ])->assertRedirect();

    $plain = (string) session('newToken');
    $resolved = PersonalAccessToken::findToken($plain);

    // Asserted through Sanctum rather than by calling the API: this test acts as
    // a logged-in user, and Sanctum falls back to the session guard, so an HTTP
    // assertion here would pass even with the bearer token removed entirely.
    expect($resolved)->not->toBeNull()
        ->and($resolved->tokenable_id)->toBe($this->user->id)
        ->and($resolved->can('time-entries:create'))->toBeTrue()
        ->and($resolved->can('invoices:delete'))->toBeFalse();
});

it('revokes a token', function () {
    $this->user->createToken('Chronos sync', ['time-entries:create']);
    $token = PersonalAccessToken::first();

    $this->delete(route('settings.api-tokens.destroy', $token->id))->assertRedirect();

    expect(PersonalAccessToken::count())->toBe(0);
});

it('stops a revoked token resolving', function () {
    $this->post(route('settings.api-tokens.store'), [
        'name' => 'Chronos sync',
        'abilities' => ['time-entries:create'],
    ])->assertRedirect();

    $plain = (string) session('newToken');
    $token = PersonalAccessToken::first();

    expect(PersonalAccessToken::findToken($plain))->not->toBeNull();

    $this->delete(route('settings.api-tokens.destroy', $token->id))->assertRedirect();

    expect(PersonalAccessToken::findToken($plain))->toBeNull();
});

it('cannot revoke another user token', function () {
    $otherUser = User::factory()->create(['type' => 'freelancer']);
    $otherUser->createToken('Theirs', ['time-entries:create']);

    $token = PersonalAccessToken::first();

    $this->delete(route('settings.api-tokens.destroy', $token->id))->assertForbidden();

    expect(PersonalAccessToken::count())->toBe(1);
});

it('does not list another user tokens', function () {
    $otherUser = User::factory()->create(['type' => 'freelancer']);
    $otherUser->createToken('Theirs', ['time-entries:create']);

    $this->get(route('settings.api-tokens'))
        ->assertInertia(fn ($page) => $page->has('tokens', 0));
});
