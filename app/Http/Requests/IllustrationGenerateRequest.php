<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IllustrationGenerateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'max:2000'],
            'negative_prompt' => ['nullable', 'string', 'max:2000'],
            'aspect_ratio' => ['nullable', 'string', Rule::in(['1:1', '2:3', '3:2', '4:3', '3:4', '16:9', '9:16'])],
            'output_format' => ['nullable', 'string', Rule::in(['png', 'jpg', 'webp'])],
            'output_quality' => ['nullable', 'integer', 'between:1,100'],
            'guidance' => ['nullable', 'numeric', 'between:0,20'],
            'seed' => ['nullable', 'integer', 'min:0'],
            'steps' => ['nullable', 'integer', 'between:1,50'],
            'safety_tolerance' => ['nullable', 'integer', 'between:1,5'],
        ];
    }
}
