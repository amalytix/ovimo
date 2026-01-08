<?php

namespace App\Http\Requests;

use App\Models\BackgroundSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContentPieceRequest extends FormRequest
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
        $teamId = auth()->user()->current_team_id;

        return [
            'internal_name' => ['required', 'string', 'max:255'],
            'published_at' => ['nullable', 'date', 'after_or_equal:now'],

            // Background sources
            'sources' => ['nullable', 'array'],
            'sources.*.type' => ['required', Rule::in([BackgroundSource::TYPE_POST, BackgroundSource::TYPE_MANUAL])],
            'sources.*.post_id' => ['nullable', 'exists:posts,id'],
            'sources.*.title' => ['nullable', 'string', 'max:500'],
            'sources.*.content' => ['nullable', 'string'],
            'sources.*.url' => ['nullable', 'url', 'max:2000'],
            'sources.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'published_at.after_or_equal' => 'Publish date cannot be in the past.',
        ];
    }
}
