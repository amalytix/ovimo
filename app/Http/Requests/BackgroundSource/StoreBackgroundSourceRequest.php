<?php

namespace App\Http\Requests\BackgroundSource;

use App\Models\BackgroundSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBackgroundSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $contentPieceId = $this->route('contentPiece')->id;

        return [
            'type' => ['required', Rule::in([BackgroundSource::TYPE_POST, BackgroundSource::TYPE_MANUAL])],
            'post_id' => [
                'required_if:type,'.BackgroundSource::TYPE_POST,
                'nullable',
                'exists:posts,id',
                Rule::unique('background_sources')->where(function ($query) use ($contentPieceId) {
                    return $query->where('content_piece_id', $contentPieceId);
                }),
            ],
            'title' => ['required_if:type,'.BackgroundSource::TYPE_MANUAL, 'nullable', 'string', 'max:500'],
            'content' => ['nullable', 'string'],
            'url' => ['nullable', 'url', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'post_id.required_if' => 'A post is required when adding a research post source.',
            'title.required_if' => 'A title is required when adding a manual source.',
        ];
    }
}
