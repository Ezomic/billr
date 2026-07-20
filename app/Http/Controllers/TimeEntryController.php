<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Concerns\InteractsWithCurrentUser;
use App\Http\Requests\TimeEntry\StoreTimeEntryRequest;
use App\Models\Project;
use App\Models\TimeEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class TimeEntryController extends Controller
{
    use InteractsWithCurrentUser;

    public function index(): Response
    {
        $user = $this->currentUser();
        $workspace = $user->requireCurrentWorkspace();

        $entries = TimeEntry::query()
            ->where('user_id', $user->id)
            ->whereHas('project', fn ($q) => $q->where('workspace_id', $workspace->id))
            ->with('project:id,name,client_id', 'project.client:id,name')
            ->orderByDesc('started_at')
            ->paginate(50);

        $projects = $workspace->projects()
            ->where('status', 'active')
            ->with('client:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'client_id', 'hourly_rate']);

        $running = TimeEntry::where('user_id', $user->id)
            ->whereNull('stopped_at')
            ->with('project:id,name')
            ->first();

        return Inertia::render('time/Index', [
            'entries' => $entries,
            'projects' => $projects,
            'running' => $running,
        ]);
    }

    public function store(StoreTimeEntryRequest $request): RedirectResponse
    {
        $user = $this->currentUser();
        $workspace = $user->requireCurrentWorkspace();

        /** @var Project $project */
        $project = $workspace->projects()->where('id', (int) $request->validated('project_id'))->firstOrFail();

        $data = $request->validated();

        if (isset($data['stopped_at'], $data['started_at'])) {
            $start = now()->parse((string) $data['started_at']);
            $stop = now()->parse((string) $data['stopped_at']);
            $data['duration_minutes'] = (int) $start->diffInMinutes($stop);
        }

        $data['user_id'] = $user->id;
        $data['hourly_rate'] = $project->hourly_rate;

        TimeEntry::create($data);

        return back()->with('success', 'Time entry saved.');
    }

    public function start(int $projectId): RedirectResponse
    {
        $user = $this->currentUser();
        $workspace = $user->requireCurrentWorkspace();

        $workspace->projects()->findOrFail($projectId);

        TimeEntry::where('user_id', $user->id)->whereNull('stopped_at')->delete();

        TimeEntry::create([
            'project_id' => $projectId,
            'user_id' => $user->id,
            'started_at' => now(),
            'billable' => true,
            'hourly_rate' => $workspace->projects()->find($projectId)?->hourly_rate,
        ]);

        return back()->with('success', 'Timer started.');
    }

    public function stop(TimeEntry $entry): RedirectResponse
    {
        abort_unless($entry->user_id === Auth::id(), 403);

        $stopped = now();
        $duration = (int) $entry->started_at->diffInMinutes($stopped);

        $entry->update([
            'stopped_at' => $stopped,
            'duration_minutes' => $duration,
        ]);

        return back()->with('success', 'Timer stopped.');
    }

    public function update(StoreTimeEntryRequest $request, TimeEntry $entry): RedirectResponse
    {
        abort_unless($entry->user_id === Auth::id(), 403);

        $data = $request->validated();

        if (isset($data['stopped_at'], $data['started_at'])) {
            $start = now()->parse((string) $data['started_at']);
            $stop = now()->parse((string) $data['stopped_at']);
            $data['duration_minutes'] = (int) $start->diffInMinutes($stop);
        }

        $entry->update($data);

        return back()->with('success', 'Entry updated.');
    }

    public function destroy(TimeEntry $entry): RedirectResponse
    {
        abort_unless($entry->user_id === Auth::id(), 403);

        $entry->delete();

        return back()->with('success', 'Entry deleted.');
    }
}
