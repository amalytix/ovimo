<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTeamSettingsRequest extends FormRequest
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
            'notifications_enabled' => ['boolean'],
            'webhook_url' => ['nullable', 'url', 'max:500'],
            'post_auto_hide_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'monthly_token_limit' => ['nullable', 'integer', 'min:0'],
            'relevancy_prompt' => ['nullable', 'string', 'max:5000'],
            'positive_keywords' => ['nullable', 'string', 'max:10000'],
            'negative_keywords' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
