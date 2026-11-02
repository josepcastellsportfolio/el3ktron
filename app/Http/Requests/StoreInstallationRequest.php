<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInstallationRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'store' => ['required', 'string', 'exists:stores,code'],
            'planogram' => ['required', 'string', 'exists:planograms,code'],
            'scheduled_for' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }
}
