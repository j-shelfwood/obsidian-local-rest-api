<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => 'required|array',
            'items.*.path' => 'required|string',
            'items.*.front_matter' => 'sometimes|nullable|array',
            'items.*.content' => 'sometimes|nullable|string',
        ];
    }
}
