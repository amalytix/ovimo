<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:500'],
            'event' => ['required', 'in:NEW_POSTS,HIGH_RELEVANCY_POST,CONTENT_GENERATED'],
            'is_active' => ['boolean'],
            'secret' => ['nullable', 'string', 'max:255'],
        ];
    }
}
