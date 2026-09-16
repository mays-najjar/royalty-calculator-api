<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CalculateRoyaltyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Optional: when omitted, the API uses the units already recorded
            // in the sales table for this release.
            'units' => ['nullable', 'integer', 'min:0', 'max:100000000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'units.integer' => 'The units field must be a whole number.',
            'units.min' => 'The units field cannot be negative.',
        ];
    }
}
