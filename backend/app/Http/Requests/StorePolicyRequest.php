<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        // Support both customer_name and insured_name
        if (!$this->has('insured_name') && $this->has('customer_name')) {
            $merge['insured_name'] = $this->input('customer_name');
        } elseif ($this->has('insured_name') && !$this->has('customer_name')) {
            $merge['customer_name'] = $this->input('insured_name');
        }

        // Support default email if not provided in intake
        if (!$this->has('insured_email')) {
            $nameSlug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string) ($this->input('customer_name') ?? $this->input('insured_name') ?? 'customer')));
            $merge['insured_email'] = $nameSlug . '@policyflow.local';
        }

        // Support both plan and plan_code
        if (!$this->has('plan') && $this->has('plan_code')) {
            $merge['plan'] = $this->input('plan_code');
        } elseif ($this->has('plan') && !$this->has('plan_code')) {
            $merge['plan_code'] = $this->input('plan');
        }

        // Support both coverage_type and sum_insured
        if (!$this->has('coverage_type') && $this->has('sum_insured')) {
            $merge['coverage_type'] = (string) $this->input('sum_insured');
        } elseif ($this->has('coverage_type') && !$this->has('sum_insured')) {
            $merge['sum_insured'] = $this->input('coverage_type');
        }

        if (!empty($merge)) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        return [
            // Idempotency key (required)
            'agent_id'          => 'required|string|max:100',
            'client_request_id' => 'required|string|max:100',

            // Insured details
            'insured_name'      => 'required|string|max:255',
            'customer_name'     => 'nullable|string|max:255',
            'insured_email'     => 'nullable|email|max:255',
            'date_of_birth'     => 'required|date_format:Y-m-d',

            // Trip details
            'destination'       => 'required|string|max:100',
            'trip_start'        => 'required|date_format:Y-m-d',
            'trip_end'          => 'required|date_format:Y-m-d|after_or_equal:trip_start',

            // Plan / coverage
            'plan'              => 'required|string|max:50',
            'plan_code'         => 'nullable|string|max:50',
            'coverage_type'     => 'required|string|max:50',
            'sum_insured'       => 'nullable',
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
