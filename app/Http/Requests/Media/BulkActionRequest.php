<?php

namespace App\Http\Requests\Media;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkActionRequest extends FormRequest
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
            'media_ids' => ['required', 'array', 'min:1'],
            'media_ids.*' => [
                Rule::exists('media', 'id')->where('team_id', auth()->user()?->current_team_id),
            ],
            'tag_ids' => ['sometimes', 'array'],
            'tag_ids.*' => [
                Rule::exists('media_tags', 'id')->where('team_id', auth()->user()?->current_team_id),
            ],
            'action' => ['sometimes', Rule::in(['add_tags', 'remove_tags'])],
        ];
    }
}
