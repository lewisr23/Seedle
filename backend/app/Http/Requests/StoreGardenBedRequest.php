<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGardenBedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'hardiness_zone' => ['nullable', 'string', 'max:10'],
            // 30cm to 30m a side. Below that there is nothing to plan, and
            // above it you have a field rather than a bed.
            'width_cm' => ['nullable', 'integer', 'min:30', 'max:3000'],
            'length_cm' => ['nullable', 'integer', 'min:30', 'max:3000'],
        ];
    }

    public function messages(): array
    {
        return [
            'width_cm.min' => 'A bed needs to be at least 30cm across.',
            'length_cm.min' => 'A bed needs to be at least 30cm deep.',
            'width_cm.max' => 'That is wider than 30m. Split it into separate beds.',
            'length_cm.max' => 'That is deeper than 30m. Split it into separate beds.',
        ];
    }
}
