<?php

namespace App\Http\Requests;

use App\Enums\ProductCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['required', new Enum(ProductCategory::class)],
            'plant_id' => ['nullable', 'integer', 'exists:plants,id'],
            'stock' => ['required', 'integer', 'min:0'],
            'images' => ['sometimes', 'array'],
            'images.*' => ['string'],
        ];
    }
}
