<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSourceRequest extends FormRequest
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
            'internal_name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['RSS', 'XML_SITEMAP'])],
            'url' => ['required', 'url', 'max:2048'],
            'monitoring_interval' => ['required', Rule::in([
                'EVERY_10_MIN', 'EVERY_30_MIN', 'HOURLY',
                'EVERY_6_HOURS', 'DAILY', 'WEEKLY',
            ])],
            'is_active' => ['boolean'],
            'should_notify' => ['boolean'],
            'auto_summarize' => ['boolean'],
            'tags' => ['array'],
            'tags.*' => ['string', 'max:50'],
        ];
    }
}
