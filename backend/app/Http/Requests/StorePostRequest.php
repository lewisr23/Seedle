<?php

namespace App\Http\Requests;

use App\Enums\PostType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', new Enum(PostType::class)],
            'body' => ['required', 'string', 'max:2000'],
            'plant_id' => ['nullable', 'integer', 'exists:plants,id'],
        ];
    }
}
