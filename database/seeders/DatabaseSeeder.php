<?php

namespace Database\Seeders;

use App\Actions\CreateWorkspace;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $user = User::create([
            'name' => 'Dev User',
            'email' => 'dev@billr.test',
            'password' => Hash::make('password'),
            'type' => 'freelancer',
        ]);

        app(CreateWorkspace::class)->handle($user, 'Dev Workspace');
    }
}
