<?php

namespace App\Http\Requests\ContentDerivative;

use App\Models\ContentDerivative;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContentDerivativeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $teamId = auth()->user()->current_team_id;
        $contentPieceId = $this->route('contentPiece')->id;

        return [
            'channel_id' => [
                'required',
                Rule::exists('channels', 'id')->where('team_id', $teamId),
                Rule::unique('content_derivatives')->where('content_piece_id', $contentPieceId),
            ],
            'prompt_id' => ['nullable', Rule::exists('prompts', 'id')->where('team_id', $teamId)],
            'title' => ['nullable', 'string', 'max:500'],
            'text' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::in([
                ContentDerivative::STATUS_NOT_STARTED,
                ContentDerivative::STATUS_DRAFT,
                ContentDerivative::STATUS_FINAL,
                ContentDerivative::STATUS_PUBLISHED,
                ContentDerivative::STATUS_NOT_PLANNED,
            ])],
            'planned_publish_at' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'channel_id.unique' => 'A derivative for this channel already exists.',
        ];
    }
}
