<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AcceptClientInvitation
{
    public function handle(Invitation $invitation, string $name, string $password): User
    {
        $user = User::firstOrCreate(
            ['email' => $invitation->email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'type' => 'client',
            ],
        );

        $client = $invitation->client;

        if (! $client->portalUsers()->where('user_id', $user->id)->exists()) {
            $client->portalUsers()->attach($user->id);
        }

        $invitation->update(['accepted_at' => now()]);

        return $user;
    }
}
