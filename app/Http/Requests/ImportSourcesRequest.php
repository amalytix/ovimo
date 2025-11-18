<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportSourcesRequest extends FormRequest
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
            'file' => ['required', 'file', 'mimes:json', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Please select a JSON file to import.',
            'file.mimes' => 'The file must be a valid JSON file.',
            'file.max' => 'The file size must not exceed 10MB.',
            'sources.required' => 'The JSON file must contain a sources array.',
            'sources.array' => 'The JSON file format is invalid. Expected an array of sources.',
            'sources.max' => 'Cannot import more than 1000 sources at once.',
            'sources.*.internal_name.required' => 'Each source must have an internal_name.',
            'sources.*.type.required' => 'Each source must have a type.',
            'sources.*.type.in' => 'Source type must be one of: RSS, XML_SITEMAP, WEBSITE.',
            'sources.*.url.required' => 'Each source must have a URL.',
            'sources.*.url.url' => 'Each source URL must be a valid URL.',
            'sources.*.monitoring_interval.required' => 'Each source must have a monitoring_interval.',
            'sources.*.monitoring_interval.in' => 'Monitoring interval must be one of: EVERY_10_MIN, EVERY_30_MIN, HOURLY, EVERY_6_HOURS, DAILY, WEEKLY.',
        ];
    }
}
