<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FileStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'path' => 'required|string',
            'type' => 'nullable|in:file,directory',
            'content' => 'nullable|string',
        ];
    }
}
