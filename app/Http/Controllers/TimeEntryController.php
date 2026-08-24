<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Concerns\InteractsWithCurrentUser;
use App\Http\Requests\TimeEntry\StoreTimeEntryRequest;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Services\CsvExporter;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TimeEntryController extends Controller
{
    use InteractsWithCurrentUser;

    public function index(Request $request): Response
    {
        $user = $this->currentUser();
        $workspace = $user->requireCurrentWorkspace();

        $isOwner = $workspace->owner_id === $user->id;
        $viewingUserId = $this->resolveViewedUserId($request, $isOwner);

        $entries = $this->filteredEntries($request, $workspace->id, $viewingUserId)
            ->with('project:id,name,client_id', 'project.client:id,name', 'user:id,name')
            ->paginate(50)
            ->withQueryString();

        // Totals cover the whole filtered set, not just the page being shown:
        // "how much is this" is the question the filters exist to answer.
        $totals = $this->filteredTotals($request, $workspace->id, $viewingUserId);

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
            'isOwner' => $isOwner,
            'members' => $isOwner
                ? $workspace->members()->orderBy('name')->get(['users.id', 'users.name'])
                : [],
            'filterProjects' => $workspace->projects()
                ->with('client:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'client_id']),
            'totals' => $totals,
            'filters' => [
                'user_id' => $viewingUserId === null ? 'all' : (string) $viewingUserId,
                'project_id' => $this->projectFilter($request, $workspace->id) ?? '',
                'from' => $this->dateFilter($request, 'from'),
                'to' => $this->dateFilter($request, 'to'),
            ],
        ]);
    }

    /** @return Builder<TimeEntry> */
    private function filteredEntries(Request $request, int $workspaceId, ?int $viewingUserId): Builder
    {
        $projectId = $this->projectFilter($request, $workspaceId);
        $from = $this->dateFilter($request, 'from');
        $to = $this->dateFilter($request, 'to');

        return TimeEntry::query()
            ->when($viewingUserId !== null, fn ($q) => $q->where('user_id', $viewingUserId))
            ->whereHas('project', fn ($q) => $q->where('workspace_id', $workspaceId))
            ->when($projectId !== null, fn ($q) => $q->where('project_id', $projectId))
            ->when($from !== '', fn ($q) => $q->whereDate('started_at', '>=', $from))
            ->when($to !== '', fn ($q) => $q->whereDate('started_at', '<=', $to))
            ->orderByDesc('started_at');
    }

    /** @return array{minutes: int, amount: int} */
    private function filteredTotals(Request $request, int $workspaceId, ?int $viewingUserId): array
    {
        $minutes = 0;
        $amount = 0;

        $entries = $this->filteredEntries($request, $workspaceId, $viewingUserId)
            ->with('project:id,hourly_rate')
            ->lazy();

        foreach ($entries as $entry) {
            $entryMinutes = $entry->duration_minutes ?? 0;
            $project = $entry->project;
            $projectRate = $project !== null ? $project->hourly_rate : null;
            $rate = $entry->hourly_rate ?? $projectRate ?? 0;

            $minutes += $entryMinutes;
            $amount += (int) round(($entryMinutes / 60) * $rate);
        }

        return ['minutes' => $minutes, 'amount' => $amount];
    }

    /** Only a project inside the workspace counts, so the filter cannot leak. */
    private function projectFilter(Request $request, int $workspaceId): ?int
    {
        $projectId = $request->integer('project_id');

        if ($projectId <= 0) {
            return null;
        }

        return Project::where('workspace_id', $workspaceId)->whereKey($projectId)->exists()
            ? $projectId
            : null;
    }

    private function dateFilter(Request $request, string $key): string
    {
        $value = trim($request->string($key)->toString());

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : '';
    }

    /**
     * Null means every member. Only an owner may widen past their own entries,
     * and only to a member of the workspace they own.
     */
    private function resolveViewedUserId(Request $request, bool $isOwner): ?int
    {
        $user = $this->currentUser();

        if (! $isOwner) {
            return $user->id;
        }

        $requested = $request->string('user_id')->toString();

        if ($requested === 'all') {
            return null;
        }

        if ($requested === '') {
            return $user->id;
        }

        $workspace = $user->requireCurrentWorkspace();
        $memberId = (int) $requested;

        return $workspace->members()->where('users.id', $memberId)->exists()
            ? $memberId
            : $user->id;
    }

    public function export(Request $request, CsvExporter $csv): StreamedResponse
    {
        $user = $this->currentUser();
        $workspace = $user->requireCurrentWorkspace();

        $isOwner = $workspace->owner_id === $user->id;

        // Same builder as the list, so the file matches what is on screen.
        $entries = $this->filteredEntries($request, $workspace->id, $this->resolveViewedUserId($request, $isOwner))
            ->with('project:id,name,client_id,hourly_rate', 'project.client:id,name', 'invoices:id,number');

        return $csv->stream(
            $csv->filename('time-entries'),
            ['Date', 'Client', 'Project', 'Description', 'Minutes', 'Hours', 'Rate', 'Amount', 'Billable', 'Billed', 'Invoice'],
            $this->timeEntryRows($entries, $csv),
        );
    }

    /**
     * @param  Builder<TimeEntry>  $entries
     * @return Generator<int, list<string|int|null>>
     */
    private function timeEntryRows(Builder $entries, CsvExporter $csv): Generator
    {
        foreach ($entries->lazy() as $entry) {
            $project = $entry->project;
            $minutes = $entry->duration_minutes ?? 0;
            $projectRate = $project !== null ? $project->hourly_rate : null;
            $rate = $entry->hourly_rate ?? $projectRate ?? 0;
            $invoice = $entry->invoices->first();

            yield [
                $entry->started_at->toDateString(),
                $project?->client?->name,
                $project?->name,
                $entry->description,
                $minutes,
                number_format($minutes / 60, 2, '.', ''),
                $csv->money($rate),
                $csv->money((int) round(($minutes / 60) * $rate)),
                $entry->billable ? 'yes' : 'no',
                $invoice ? 'yes' : 'no',
                $invoice?->number,
            ];
        }
    }

    public function store(StoreTimeEntryRequest $request): RedirectResponse
    {
        $user = $this->currentUser();
        $workspace = $user->requireCurrentWorkspace();

        /** @var Project $project */
        $project = $workspace->projects()->where('id', $request->integer('project_id'))->firstOrFail();

        $data = $request->validated();

        if (isset($data['stopped_at'], $data['started_at'])) {
            $start = now()->parse($request->string('started_at')->toString());
            $stop = now()->parse($request->string('stopped_at')->toString());
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

        /** @var Project $project */
        $project = $workspace->projects()->findOrFail($projectId);

        // Switching projects must not throw away the running entry: stop it and
        // keep the time, the way the stop action does.
        TimeEntry::where('user_id', $user->id)
            ->whereNull('stopped_at')
            ->get()
            ->each(fn (TimeEntry $running) => $this->stopEntry($running));

        TimeEntry::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'started_at' => now(),
            'billable' => true,
            'hourly_rate' => $project->hourly_rate,
        ]);

        return back()->with('success', 'Timer started.');
    }

    public function stop(TimeEntry $entry): RedirectResponse
    {
        abort_unless($entry->user_id === Auth::id(), 403);

        $this->stopEntry($entry);

        return back()->with('success', 'Timer stopped.');
    }

    private function stopEntry(TimeEntry $entry): void
    {
        $stopped = now();

        $entry->update([
            'stopped_at' => $stopped,
            'duration_minutes' => (int) $entry->started_at->diffInMinutes($stopped),
        ]);
    }

    public function update(StoreTimeEntryRequest $request, TimeEntry $entry): RedirectResponse
    {
        abort_unless($entry->user_id === Auth::id(), 403);
        $this->abortIfBilled($entry);

        $data = $request->validated();

        if (isset($data['stopped_at'], $data['started_at'])) {
            $start = now()->parse($request->string('started_at')->toString());
            $stop = now()->parse($request->string('stopped_at')->toString());
            $data['duration_minutes'] = (int) $start->diffInMinutes($stop);
        }

        $entry->update($data);

        return back()->with('success', 'Entry updated.');
    }

    public function destroy(TimeEntry $entry): RedirectResponse
    {
        abort_unless($entry->user_id === Auth::id(), 403);
        $this->abortIfBilled($entry);

        $entry->delete();

        return back()->with('success', 'Entry deleted.');
    }

    // Editing billed time would leave the invoice line stating an amount the
    // hours behind it no longer support. Voiding detaches the entries, so an
    // entry from a voided invoice is editable again.
    private function abortIfBilled(TimeEntry $entry): void
    {
        abort_if(
            $entry->invoices()->exists(),
            422,
            'This entry is on an invoice. Void that invoice first to change it.',
        );
    }
}
