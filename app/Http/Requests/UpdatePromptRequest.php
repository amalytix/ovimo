<?php

namespace App\Http\Requests;

use App\Models\Prompt;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePromptRequest extends FormRequest
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
        $type = $this->input('type', $this->route('prompt')->type ?? Prompt::TYPE_CONTENT);

        return [
            'internal_name' => ['required', 'string', 'max:255'],
            'type' => ['sometimes', 'string', Rule::in([Prompt::TYPE_CONTENT, Prompt::TYPE_IMAGE])],
            'channel' => $type === Prompt::TYPE_CONTENT
                ? ['required', 'string', 'in:BLOG_POST,LINKEDIN_POST,YOUTUBE_SCRIPT']
                : ['nullable', 'string', 'in:BLOG_POST,LINKEDIN_POST,YOUTUBE_SCRIPT'],
            'prompt_text' => ['required', 'string', 'max:10000'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
