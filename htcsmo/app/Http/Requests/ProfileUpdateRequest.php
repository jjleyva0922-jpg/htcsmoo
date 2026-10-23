<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'username' => [
                'bail',
                'required',
                'string',
                // Existing login names may predate the current registration rules.
                Rule::when($this->input('username') !== $this->user()->username, [
                    'min:4', 'max:30', 'alpha_dash:ascii',
                ]),
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'email' => ['exclude'],
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    /**
     * Normalize profile values before validation.
     */
    protected function prepareForValidation(): void
    {
        $normalized = [];
        $value = $this->input('username');
        if (is_string($value)) {
            $value = trim($value);
            $normalized['username'] = $value === $this->user()->username ? $value : strtolower($value);
        }
        $this->merge($normalized);
    }
}
