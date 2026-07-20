<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Concerns\InteractsWithCurrentUser;
use App\Http\Requests\Project\UpsertProjectRequest;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    use InteractsWithCurrentUser;

    public function index(): Response
    {
        $workspace = $this->currentUser()->requireCurrentWorkspace();

        $projects = $workspace->projects()
            ->with('client:id,name')
            ->withCount('timeEntries')
            ->orderBy('status')
            ->orderBy('name')
            ->get();

        $clients = $workspace->clients()->orderBy('name')->get(['id', 'name']);

        return Inertia::render('projects/Index', [
            'projects' => $projects,
            'clients' => $clients,
        ]);
    }

    public function store(UpsertProjectRequest $request): RedirectResponse
    {
        $workspace = $this->currentUser()->requireCurrentWorkspace();

        abort_unless(
            $workspace->clients()->where('id', $request->validated('client_id'))->exists(),
            403,
        );

        $workspace->projects()->create($request->validated());

        return back()->with('success', 'Project created.');
    }

    public function update(UpsertProjectRequest $request, Project $project): RedirectResponse
    {
        $this->authorizeProject($project);

        $project->update($request->validated());

        return back()->with('success', 'Project updated.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        $this->authorizeProject($project);

        $project->delete();

        return back()->with('success', 'Project deleted.');
    }

    private function authorizeProject(Project $project): void
    {
        abort_unless($project->workspace_id === $this->currentUser()->current_workspace_id, 403);
    }
}
