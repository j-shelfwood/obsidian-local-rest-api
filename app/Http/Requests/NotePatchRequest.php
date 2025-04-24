<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class NotePatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // For PATCH (update), fields are optional
            'front_matter' => 'sometimes|nullable|array',
            'content' => 'sometimes|nullable|string',
        ];
    }
}
