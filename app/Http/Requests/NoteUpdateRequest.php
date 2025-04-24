<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class NoteUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // For PUT (replace), all fields are expected
            'front_matter' => 'present|nullable|array',
            'content' => 'present|nullable|string',
        ];
    }
}
