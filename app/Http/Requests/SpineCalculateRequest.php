<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SpineCalculateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page_count' => ['required', 'integer', 'min:1'],
            'paper_caliper_mm' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
