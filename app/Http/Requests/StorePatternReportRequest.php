<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePatternReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && (int) $this->route('discussionPattern')->user_id !== (int) $this->user()->id;
    }

    public function rules(): array
    {
        return [
            'topic' => ['required', Rule::in(array_keys(__('pattern_reports.topics')))],
            'message' => ['required', 'string', 'max:4000'],
        ];
    }
}
