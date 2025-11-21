<?php

namespace App\Http\Requests\Media;

use Illuminate\Foundation\Http\FormRequest;

class PresignMediaRequest extends FormRequest
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
            'filename' => ['required', 'string', 'max:255'],
            'mime_type' => ['required', 'string', 'in:image/jpeg,image/png,image/gif,image/webp,image/svg+xml,application/pdf'],
            'file_size' => ['required', 'integer', 'min:1', 'max:52428800'],
        ];
    }
}
