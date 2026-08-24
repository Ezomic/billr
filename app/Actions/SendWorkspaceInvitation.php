<?php

declare(strict_types=1);

namespace App\Actions;

use App\Mail\WorkspaceInvitationMail;
use App\Models\Invitation;
use App\Models\Workspace;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SendWorkspaceInvitation
{
    public const EXPIRES_AFTER_DAYS = 7;

    /**
     * Reuses the pending invitation for an address rather than stacking up a new
     * row per click, so resending refreshes the expiry and mails the same link
     * instead of leaving several valid tokens outstanding.
     */
    public function handle(Workspace $workspace, string $email, string $role): Invitation
    {
        $invitation = $workspace->invitations()
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->first();

        if ($invitation === null) {
            $invitation = $workspace->invitations()->create([
                'email' => $email,
                'role' => $role,
                'token' => Str::random(64),
                'expires_at' => now()->addDays(self::EXPIRES_AFTER_DAYS),
            ]);
        } else {
            $invitation->update([
                'role' => $role,
                'expires_at' => now()->addDays(self::EXPIRES_AFTER_DAYS),
            ]);
        }

        $invitation->loadMissing('workspace');

        Mail::to($email)->send(new WorkspaceInvitationMail($invitation));

        return $invitation;
    }
}
