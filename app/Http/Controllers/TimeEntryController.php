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
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function export(CsvExporter $csv): StreamedResponse
    {
        $user = $this->currentUser();
        $workspace = $user->requireCurrentWorkspace();

        $entries = TimeEntry::query()
            ->where('user_id', $user->id)
            ->whereHas('project', fn ($q) => $q->where('workspace_id', $workspace->id))
            ->with('project:id,name,client_id,hourly_rate', 'project.client:id,name', 'invoices:id,number')
            ->orderByDesc('started_at');

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
