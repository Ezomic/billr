<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\IngestExternalTimeEntry;
use App\Concerns\InteractsWithCurrentUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IngestTimeEntryRequest;
use Illuminate\Http\JsonResponse;

class TimeEntryController extends Controller
{
    use InteractsWithCurrentUser;

    public function store(IngestTimeEntryRequest $request, IngestExternalTimeEntry $action): JsonResponse
    {
        $entry = $action->handle(
            user: $this->currentUser(),
            externalSource: $request->string('external_source')->toString(),
            externalRef: $request->string('external_ref')->toString(),
            minutes: $request->integer('minutes'),
            spentOn: $request->string('spent_on')->toString(),
            description: $request->input('description'),
            billable: $request->boolean('billable', true),
            billrProjectId: $request->integer('billr_project_id') ?: null,
            clientName: $request->input('client_name'),
            projectName: $request->input('project_name'),
        );

        return response()->json([
            'time_entry_id' => $entry->id,
            'project_id' => $entry->project_id,
            'client_id' => $entry->project?->client_id,
            'billable' => $entry->billable,
        ], 201);
    }
}
