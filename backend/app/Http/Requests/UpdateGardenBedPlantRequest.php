<?php

namespace App\Http\Requests;

use App\Models\GardenBed;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Moving or annotating something already in a bed.
 *
 * Every field is `sometimes`, because dragging a plant across the plot sends
 * coordinates and nothing else, and it should not have to resend the notes to
 * avoid wiping them.
 */
class UpdateGardenBedPlantRequest extends FormRequest
{
    use ValidatesPlotPosition;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'x_cm' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:3000'],
            'y_cm' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:3000'],
            'planted_at' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:500'],
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
