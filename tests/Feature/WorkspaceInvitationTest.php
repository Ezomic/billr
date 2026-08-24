<?php

declare(strict_types=1);

use App\Mail\WorkspaceInvitationMail;
use App\Models\Invitation;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();

    $this->owner = User::factory()->create(['type' => 'freelancer']);
    $this->workspace = Workspace::create([
        'name' => 'Test Workspace',
        'slug' => 'test-workspace',
        'owner_id' => $this->owner->id,
        'currency' => 'USD',
        'timezone' => 'UTC',
    ]);
    $this->workspace->members()->attach($this->owner->id, ['role' => 'owner']);
    $this->owner->update(['current_workspace_id' => $this->workspace->id]);

    $this->actingAs($this->owner);
});

it('emails the invitation when someone is invited', function () {
    $this->post(route('settings.members.invite'), [
        'email' => 'newcomer@example.com',
        'role' => 'member',
    ])->assertRedirect();

    $invitation = Invitation::first();

    expect($invitation)->not->toBeNull()
        ->and($invitation->email)->toBe('newcomer@example.com');

    Mail::assertSent(WorkspaceInvitationMail::class, fn ($mail) => $mail->hasTo('newcomer@example.com')
        && $mail->invitation->is($invitation));
});

it('sends a link that actually reaches the accept page', function () {
    $this->post(route('settings.members.invite'), [
        'email' => 'newcomer@example.com',
        'role' => 'member',
    ])->assertRedirect();

    $token = Invitation::first()->token;

    // The token is the whole point of the email: if this route does not resolve,
    // the invitee has no way in, which is exactly the bug this ticket describes.
    auth()->logout();
    $this->get(route('invitations.show', $token))->assertOk();
});

it('reuses the pending invitation instead of stacking up tokens', function () {
    foreach (range(1, 3) as $ignored) {
        $this->post(route('settings.members.invite'), [
            'email' => 'newcomer@example.com',
            'role' => 'member',
        ])->assertRedirect();
    }

    expect(Invitation::where('email', 'newcomer@example.com')->count())->toBe(1);
    Mail::assertSentCount(3);
});

it('refreshes the expiry when an invitation is resent', function () {
    $this->post(route('settings.members.invite'), [
        'email' => 'newcomer@example.com',
        'role' => 'member',
    ])->assertRedirect();

    $invitation = Invitation::first();
    $invitation->forceFill(['expires_at' => now()->addHour()])->save();

    $this->post(route('settings.members.invitations.resend', $invitation))->assertRedirect();

    expect($invitation->fresh()->expires_at->isAfter(now()->addDays(6)))->toBeTrue();
    Mail::assertSentCount(2);
});

it('cancels a pending invitation', function () {
    $this->post(route('settings.members.invite'), [
        'email' => 'newcomer@example.com',
        'role' => 'member',
    ])->assertRedirect();

    $invitation = Invitation::first();

    $this->delete(route('settings.members.invitations.cancel', $invitation))->assertRedirect();

    expect(Invitation::count())->toBe(0);
});

it('refuses to invite somebody who is already a member', function () {
    $existing = User::factory()->create(['type' => 'freelancer', 'email' => 'already@example.com']);
    $this->workspace->members()->attach($existing->id, ['role' => 'member']);

    $this->post(route('settings.members.invite'), [
        'email' => 'already@example.com',
        'role' => 'member',
    ])->assertStatus(422);

    Mail::assertNothingSent();
    expect(Invitation::count())->toBe(0);
});

it('only lets the owner invite', function () {
    $member = User::factory()->create(['type' => 'freelancer']);
    $this->workspace->members()->attach($member->id, ['role' => 'member']);
    $member->update(['current_workspace_id' => $this->workspace->id]);

    $this->actingAs($member)
        ->post(route('settings.members.invite'), [
            'email' => 'newcomer@example.com',
            'role' => 'member',
        ])->assertForbidden();

    Mail::assertNothingSent();
});

it('cannot resend or cancel another workspace invitation', function () {
    $otherOwner = User::factory()->create(['type' => 'freelancer']);
    $otherWorkspace = Workspace::create([
        'name' => 'Other',
        'slug' => 'other-ws-invite',
        'owner_id' => $otherOwner->id,
        'currency' => 'USD',
        'timezone' => 'UTC',
    ]);
    $otherWorkspace->members()->attach($otherOwner->id, ['role' => 'owner']);

    $theirs = $otherWorkspace->invitations()->create([
        'email' => 'theirs@example.com',
        'role' => 'member',
        'token' => str_repeat('a', 64),
        'expires_at' => now()->addDays(7),
    ]);

    $this->post(route('settings.members.invitations.resend', $theirs))->assertForbidden();
    $this->delete(route('settings.members.invitations.cancel', $theirs))->assertForbidden();

    expect(Invitation::count())->toBe(1);
    Mail::assertNothingSent();
});
