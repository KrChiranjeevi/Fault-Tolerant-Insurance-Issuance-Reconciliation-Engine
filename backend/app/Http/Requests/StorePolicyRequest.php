<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Idempotency key (required)
            'agent_id'          => 'required|string|max:100',
            'client_request_id' => 'required|string|max:100',

            // Insured details
            'insured_name'      => 'required|string|max:255',
            'insured_email'     => 'required|email|max:255',
            'date_of_birth'     => 'required|date_format:Y-m-d',

            // Trip details
            'destination'       => 'required|string|max:100',
            'trip_start'        => 'required|date_format:Y-m-d',
            'trip_end'          => 'required|date_format:Y-m-d|after_or_equal:trip_start',

            // Plan / coverage
            'plan'              => 'required|string|max:50',
            'coverage_type'     => 'required|string|max:50',
            'currency'          => 'nullable|string|size:3',

            // Optional metadata passthrough
            'metadata'          => 'nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'trip_end.after_or_equal' => 'The trip end date must be on or after the trip start date.',
            'date_of_birth.date_format' => 'Date of birth must be in Y-m-d format (e.g. 1990-05-20).',
        ];
    }
}
