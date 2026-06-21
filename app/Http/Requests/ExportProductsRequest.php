<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExportProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return [
            'format' => ['nullable', 'in:csv,odt'],
        ];
    }
}
