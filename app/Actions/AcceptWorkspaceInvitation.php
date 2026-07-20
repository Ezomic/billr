<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

class AcceptWorkspaceInvitation
{
    public function handle(Invitation $invitation, string $name, string $password): User
    {
        $user = User::firstOrCreate(
            ['email' => $invitation->email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'type' => 'freelancer',
            ],
        );

        $workspace = $invitation->workspace;

        if ($workspace === null) {
            throw new InvalidArgumentException("Invitation {$invitation->id} has no workspace.");
        }

        if (! $workspace->members()->where('user_id', $user->id)->exists()) {
            $workspace->members()->attach($user->id, ['role' => $invitation->role]);
        }

        if ($user->current_workspace_id === null) {
            $user->update(['current_workspace_id' => $workspace->id]);
        }

        $invitation->update(['accepted_at' => now()]);

        return $user;
    }
}
