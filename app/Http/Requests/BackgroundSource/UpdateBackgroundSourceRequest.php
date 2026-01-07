<?php

namespace App\Http\Requests\BackgroundSource;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBackgroundSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:500'],
            'content' => ['nullable', 'string'],
            'url' => ['nullable', 'url', 'max:2000'],
        ];
    }
}
