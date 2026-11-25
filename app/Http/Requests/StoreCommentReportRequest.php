<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommentReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && (int) $this->route('discussionPattern')->user_id === (int) $this->user()->id;
    }

    public function rules(): array
    {
        return [
            'topic' => ['required', Rule::in(array_keys(__('reports.topics')))],
            'message' => ['required', 'string', 'max:4000'],
            'screenshots' => ['sometimes', 'array', 'max:3'],
            'screenshots.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }
}
