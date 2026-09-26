<?php

namespace App\Http\Requests;

use App\Models\GardenBed;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class AddGardenBedPlantRequest extends FormRequest
{
    use ValidatesPlotPosition;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plant_id' => ['required', 'integer', 'exists:plants,id'],
            'x_cm' => ['nullable', 'integer', 'min:0', 'max:3000'],
            'y_cm' => ['nullable', 'integer', 'min:0', 'max:3000'],
            'planted_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $bed = $this->route('gardenBed');

        if ($bed instanceof GardenBed) {
            $this->validatePositionWithin($validator, $bed);
        }
    }
}
