<?php

namespace App\Http\Requests;

use Illuminate\Database\Query\Builder;
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
        return [
            'internal_name' => ['required', 'string', 'max:255'],
            'prompt_id' => ['nullable', 'exists:prompts,id'],
            'briefing_text' => ['nullable', 'string', 'max:5000'],
            'channel' => ['required', 'in:BLOG_POST,LINKEDIN_POST,YOUTUBE_SCRIPT'],
            'target_language' => ['required', 'in:ENGLISH,GERMAN'],
            'post_ids' => ['nullable', 'array'],
            'post_ids.*' => ['exists:posts,id'],
            'published_at' => ['nullable', 'date', 'after_or_equal:now'],
            'research_text' => ['nullable', 'string'],
            'edited_text' => ['nullable', 'string'],
            'media_ids' => ['nullable', 'array'],
            'media_ids.*' => [
                Rule::exists('media', 'id')->where(fn (Builder $query) => $query->where('team_id', auth()->user()->current_team_id)),
            ],
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
