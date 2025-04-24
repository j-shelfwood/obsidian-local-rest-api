<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class NoteStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'path' => 'required|string',
            'front_matter' => 'nullable|array',
            'content' => 'nullable|string',
        ];
    }
}
