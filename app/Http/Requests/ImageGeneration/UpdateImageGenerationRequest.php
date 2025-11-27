<?php

namespace App\Http\Requests\ImageGeneration;

use App\Models\ImageGeneration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateImageGenerationRequest extends FormRequest
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
            'generated_text_prompt' => ['required', 'string', 'max:5000'],
            'aspect_ratio' => [
                'sometimes',
                'string',
                Rule::in([
                    ImageGeneration::ASPECT_RATIO_16_9,
                    ImageGeneration::ASPECT_RATIO_1_1,
                    ImageGeneration::ASPECT_RATIO_4_3,
                    ImageGeneration::ASPECT_RATIO_9_16,
                    ImageGeneration::ASPECT_RATIO_4_5,
                ]),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'generated_text_prompt.required' => 'The image prompt text is required.',
            'generated_text_prompt.max' => 'The image prompt text cannot exceed 5000 characters.',
            'aspect_ratio.in' => 'Please select a valid aspect ratio.',
        ];
    }
}
