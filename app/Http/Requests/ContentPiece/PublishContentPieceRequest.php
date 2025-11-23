<?php

namespace App\Http\Requests\ContentPiece;

use Illuminate\Foundation\Http\FormRequest;

class PublishContentPieceRequest extends FormRequest
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
            'integration_id' => ['required', 'integer', 'exists:social_integrations,id'],
            'schedule_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
