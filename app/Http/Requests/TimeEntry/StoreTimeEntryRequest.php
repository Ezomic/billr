<?php

declare(strict_types=1);

namespace App\Http\Requests\TimeEntry;

use Illuminate\Foundation\Http\FormRequest;

class StoreTimeEntryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'project_id'  => ['required', 'integer', 'exists:projects,id'],
            'description' => ['nullable', 'string'],
            'started_at'  => ['required', 'date'],
            'stopped_at'  => ['nullable', 'date', 'after:started_at'],
            'billable'    => ['boolean'],
        ];
    }
}
