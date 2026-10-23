<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActiveRequestIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return self::filterRules($this->user());
    }

    public static function filterRules(User $user): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in([
                'pending', 'approved_dept', 'approved_vp', 'approved_provost', 'approved_president',
                'processing', 'ready_for_pickup', 'for_clarification', 'deadline',
                ...($user->role === 'employee' ? ['action_required'] : []),
            ])],
            'dept' => [Rule::prohibitedIf($user->role !== 'smo'), 'nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'order' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
