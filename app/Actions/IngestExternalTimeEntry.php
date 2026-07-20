<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Client;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\CarbonImmutable;

class IngestExternalTimeEntry
{
    public function handle(
        User $user,
        string $externalSource,
        string $externalRef,
        int $minutes,
        string $spentOn,
        ?string $description,
        bool $billable,
        ?int $billrProjectId,
        ?string $clientName,
        ?string $projectName,
    ): TimeEntry {
        $workspace = $user->requireCurrentWorkspace();

        $project = $billrProjectId !== null
            ? Project::where('workspace_id', $workspace->id)->findOrFail($billrProjectId)
            : $this->resolveProject($workspace->id, $clientName ?? '', $projectName ?? '');

        $startedAt = CarbonImmutable::parse($spentOn)->startOfDay();

        return TimeEntry::updateOrCreate(
            ['external_source' => $externalSource, 'external_ref' => $externalRef],
            [
                'project_id' => $project->id,
                'user_id' => $user->id,
                'description' => $description,
                'started_at' => $startedAt,
                'stopped_at' => $startedAt->addMinutes($minutes),
                'duration_minutes' => $minutes,
                'billable' => $billable,
            ],
        );
    }

    private function resolveProject(int $workspaceId, string $clientName, string $projectName): Project
    {
        $client = Client::firstOrCreate([
            'workspace_id' => $workspaceId,
            'name' => $clientName,
        ]);

        return Project::firstOrCreate([
            'workspace_id' => $workspaceId,
            'client_id' => $client->id,
            'name' => $projectName,
        ], [
            'type' => 'hourly',
            'status' => 'active',
        ]);
    }
}
