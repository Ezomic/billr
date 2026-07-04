<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RegisterFreelancer
{
    public function __construct(private readonly CreateWorkspace $createWorkspace) {}

    public function handle(string $name, string $email, string $password, string $workspaceName): User
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'type' => 'freelancer',
        ]);

        $this->createWorkspace->handle($user, $workspaceName);

        return $user;
    }
}
