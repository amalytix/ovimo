<?php

namespace App\Http\Requests\ContentPiece;

use Illuminate\Foundation\Http\FormRequest;

class BulkActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'content_piece_ids' => ['required', 'array', 'min:1'],
            'content_piece_ids.*' => ['integer', 'exists:content_pieces,id'],
        ];

        // Add status validation if this is a status update request
        if ($this->routeIs('content-pieces.bulk-update-status')) {
            $rules['status'] = ['required', 'in:NOT_STARTED,DRAFT,FINAL'];
        }

        return $rules;
    }
}
