<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Concerns\InteractsWithCurrentUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IngestTimeEntryRequest extends FormRequest
{
    use InteractsWithCurrentUser;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'external_source' => ['required', 'string', 'max:60'],
            'external_ref' => ['required', 'string', 'max:100'],

            'billr_project_id' => [
                'nullable',
                'integer',
                Rule::exists('projects', 'id')->where('workspace_id', $this->currentUser()->currentWorkspace?->id),
            ],
            'client_name' => ['required_without:billr_project_id', 'string', 'max:255'],
            'project_name' => ['required_without:billr_project_id', 'string', 'max:255'],

            'minutes' => ['required', 'integer', 'min:1'],
            'spent_on' => ['required', 'date'],
            'description' => ['nullable', 'string'],
            'billable' => ['boolean'],
        ];
    }
}
