<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTeamSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        if ($this->has('openai_api_key')) {
            $payload['openai_api_key'] = $this->input('openai_api_key') === ''
                ? null
                : $this->input('openai_api_key');
        }

        if ($this->has('gemini_api_key')) {
            $payload['gemini_api_key'] = $this->input('gemini_api_key') === ''
                ? null
                : $this->input('gemini_api_key');
        }

        if (! empty($payload)) {
            $this->merge($payload);
        }
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'post_auto_hide_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'monthly_token_limit' => ['nullable', 'integer', 'min:0'],
            'relevancy_prompt' => ['nullable', 'string', 'max:5000'],
            'positive_keywords' => ['nullable', 'string', 'max:10000'],
            'negative_keywords' => ['nullable', 'string', 'max:10000'],
            'openai_api_key' => ['nullable', 'string', 'max:500'],
            'openai_model' => ['nullable', 'string', 'max:50'],
            'gemini_api_key' => ['nullable', 'string', 'max:500'],
            'gemini_image_model' => ['nullable', 'string', 'max:100'],
            'gemini_image_size' => ['nullable', 'string', 'in:1K,2K,4K'],
        ];
    }
}
