<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSourceRequest extends FormRequest
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
            'type' => ['required', Rule::in(['RSS', 'XML_SITEMAP', 'WEBSITE'])],
            'url' => ['required', 'url', 'max:2048'],
            'css_selector_title' => ['nullable', 'required_if:type,WEBSITE', 'string', 'max:500'],
            'css_selector_link' => ['nullable', 'required_if:type,WEBSITE', 'string', 'max:500'],
            'keywords' => ['nullable', 'string', 'max:1000'],
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
