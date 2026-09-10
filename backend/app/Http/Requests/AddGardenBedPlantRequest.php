<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddGardenBedPlantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plant_id' => ['required', 'integer', 'exists:plants,id'],
            'planted_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
