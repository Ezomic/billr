<?php

declare(strict_types=1);

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;

class UpsertProjectRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'client_id'   => ['required', 'integer', 'exists:clients,id'],
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type'        => ['required', 'in:hourly,fixed'],
            'hourly_rate' => ['nullable', 'integer', 'min:0', 'required_if:type,hourly'],
            'fixed_price' => ['nullable', 'integer', 'min:0', 'required_if:type,fixed'],
        ];
    }
}
