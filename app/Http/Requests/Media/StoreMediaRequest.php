<?php

namespace App\Http\Requests\Media;

use Illuminate\Foundation\Http\FormRequest;

class StoreMediaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            's3_key' => ['required', 'string'],
            'filename' => ['required', 'string', 'max:255'],
            'stored_filename' => ['required', 'string'],
            'file_path' => ['required', 'string'],
            'mime_type' => ['required', 'string'],
            'file_size' => ['required', 'integer', 'min:1'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
