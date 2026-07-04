<?php

declare(strict_types=1);

namespace App\Actions;

use App\Mail\ClientPortalAccessMail;
use App\Models\Client;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SendClientPortalAccess
{
    public function handle(Client $client): void
    {
        $token = Str::random(64);

        $client->update(['portal_token' => $token]);

        Mail::to($client->email)->send(new ClientPortalAccessMail($client, $token));
    }
}
