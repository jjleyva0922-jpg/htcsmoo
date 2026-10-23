<?php

namespace App\Http\Requests;

use App\Models\Requisition;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ArchiveIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && (! $this->routeIs('admin.archive') || $this->user()->role === 'smo');
    }

    public function rules(): array
    {
        return self::filterRules($this->user());
    }

    public static function filterRules(?User $user): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', Rule::in(array_keys(Requisition::ARCHIVE_CATEGORIES))],
            'status' => ['nullable', Rule::in(Requisition::ARCHIVED_STATUSES)],
            'dept' => [Rule::prohibitedIf($user?->role !== 'smo'), 'nullable', 'string', 'max:100'],
            'date_basis' => ['nullable', Rule::in(['closed', 'submitted'])],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'order' => ['nullable', Rule::in(['asc', 'desc'])],
            'print' => ['nullable', 'boolean'],
            'export' => ['nullable', Rule::in(['csv'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'Choose a valid archived status.',
            'date_from.date_format' => 'The start date must be a valid date.',
            'date_to.date_format' => 'The end date must be a valid date.',
            'date_to.after_or_equal' => 'The end date must be on or after the start date.',
            'order.in' => 'Choose a valid archive sort order.',
        ];
    }
}
