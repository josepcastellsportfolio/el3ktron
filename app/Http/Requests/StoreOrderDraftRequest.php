<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderDraftRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            /** The order as the store wrote it: email body or text of a PDF. */
            'text' => ['required', 'string', 'max:20000'],
            'store' => ['nullable', 'string', 'exists:stores,code'],
        ];
    }
}
