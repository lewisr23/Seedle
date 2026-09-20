<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:80'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:500'],
            'location' => ['sometimes', 'nullable', 'string', 'max:80'],
            'hardiness_zone' => ['sometimes', 'nullable', 'string', 'max:10'],
            // Loose on format: postcodes.io is the real validator, and a
            // rejected lookup simply leaves the account without coordinates.
            'postcode' => ['sometimes', 'nullable', 'string', 'max:10'],
        ];
    }
}
