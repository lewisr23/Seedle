<?php

namespace App\Http\Requests;

use App\Models\Harvest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHarvestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'harvested_at' => ['required', 'date', 'before_or_equal:today'],
            'quantity' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'unit' => ['nullable', 'string', Rule::in(Harvest::UNITS)],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'harvested_at.before_or_equal' => 'You cannot log a harvest in the future.',
        ];
    }
}
