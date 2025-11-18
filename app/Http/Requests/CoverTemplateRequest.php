<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CoverTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'height_mm' => ['required', 'numeric', 'gt:0'],
            'back_cover_mm' => ['required', 'numeric', 'gt:0'],
            'spine_mm' => ['required', 'numeric', 'gt:0'],
            'front_cover_mm' => ['required', 'numeric', 'gt:0'],
            'back_flap_mm' => ['nullable', 'numeric', 'gte:0'],
            'front_flap_mm' => ['nullable', 'numeric', 'gte:0'],
            'bleed_mm' => ['nullable', 'numeric', 'gte:0'],
        ];
    }
}
