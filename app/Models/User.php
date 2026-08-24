<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\MissingWorkspaceException;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'type', 'current_workspace_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isFreelancer(): bool
    {
        return $this->type === 'freelancer';
    }

    public function isClient(): bool
    {
        return $this->type === 'client';
    }

    /** @return BelongsTo<Workspace, $this> */
    public function currentWorkspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'current_workspace_id');
    }

    /**
     * The workspace this user is acting in, for the freelancer routes that
     * cannot render without one. current_workspace_id is nullable and the
     * access-workspace gate only checks isFreelancer(), so a freelancer who
     * has not accepted an invitation yet can still reach those routes; this
     * fails with a named exception rather than reading a property on null.
     *
     * Membership is re-checked here rather than trusted from the column.
     * Removing someone from a workspace leaves current_workspace_id pointing
     * at it, so without this check a removed member keeps full access.
     */
    public function requireCurrentWorkspace(): Workspace
    {
        $workspace = $this->usableCurrentWorkspace();

        if ($workspace === null) {
            throw new MissingWorkspaceException($this);
        }

        return $workspace;
    }

    /**
     * The current workspace, but only if the user is still a member of it.
     * Every path that resolves a workspace goes through here, including the
     * API, which does not pass through the EnsureWorkspace middleware.
     */
    public function usableCurrentWorkspace(): ?Workspace
    {
        $workspace = $this->currentWorkspace;

        if ($workspace === null || ! $this->belongsToWorkspace($workspace)) {
            return null;
        }

        return $workspace;
    }

    public function belongsToWorkspace(Workspace $workspace): bool
    {
        return $this->workspaces()->whereKey($workspace->getKey())->exists();
    }

    public function hasUsableCurrentWorkspace(): bool
    {
        return $this->usableCurrentWorkspace() !== null;
    }

    /** @return BelongsToMany<Workspace, $this> */
    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /** @return BelongsToMany<Client, $this> */
    public function accessibleClients(): BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'client_user')->withTimestamps();
    }

    /** @return HasMany<TimeEntry, $this> */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }
}
