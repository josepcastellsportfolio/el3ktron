<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CorrectOrderDraftRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1', 'max:500'],
            'lines.*.sku' => ['required', 'string', 'exists:products,sku'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
        ];
    }
}
