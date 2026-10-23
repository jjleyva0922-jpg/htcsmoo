<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApprovalQueueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['smo', 'dept_head', 'vp_finance', 'vp_admin', 'provost', 'president'], true);
    }

    public function rules(): array
    {
        return self::filterRules();
    }

    public static function filterRules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'dept' => ['nullable', 'string', 'max:100'],
            'tier' => ['nullable', Rule::in(['minor', 'major'])],
            'status' => ['nullable', Rule::in(['pending', 'approved_dept', 'approved_vp', 'approved_provost', 'approved_president', 'processing', 'ready_for_pickup', 'for_clarification'])],
            'date_from' => ['nullable', 'required_with:date_to', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'required_with:date_from', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'sort' => ['nullable', Rule::in(['priority', 'oldest', 'deadline', 'newest'])],
            'tab' => ['nullable', Rule::in(['minor', 'major', 'work', 'pending', 'clarification'])],
            'request' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
            'minor_page' => ['nullable', 'integer', 'min:1'],
            'major_page' => ['nullable', 'integer', 'min:1'],
            'work_page' => ['nullable', 'integer', 'min:1'],
            'inquiry_page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
