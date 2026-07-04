<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Str;

class CreateWorkspace
{
    public function handle(User $user, string $name): Workspace
    {
        $slug = $this->uniqueSlug($name);

        $workspace = Workspace::create([
            'name' => $name,
            'slug' => $slug,
            'owner_id' => $user->id,
            'currency' => 'EUR',
            'timezone' => 'UTC',
        ]);

        $workspace->members()->attach($user->id, ['role' => 'owner']);

        $user->update(['current_workspace_id' => $workspace->id]);

        return $workspace;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 2;

        while (Workspace::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
