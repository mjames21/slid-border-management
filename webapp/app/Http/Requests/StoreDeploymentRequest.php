<?php

namespace App\Http\Requests;

use App\Models\Country;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDeploymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'country_name' => trim((string) $this->input('country_name')),
            'agency_name' => trim((string) $this->input('agency_name')),
            'contact_name' => trim((string) $this->input('contact_name')),
            'contact_email' => strtolower(trim((string) $this->input('contact_email'))),
            'contact_phone' => trim((string) $this->input('contact_phone')),
            'contact_role' => trim((string) $this->input('contact_role')),
            'deployment_plan' => (string) $this->input('deployment_plan', Country::PLAN_PROGRAM),
            'deployment_type' => (string) $this->input('deployment_type', Country::DEPLOYMENT_HOSTED),
        ]);
    }

    public function rules(): array
    {
        return [
            'country_name' => ['required', 'string', 'max:120'],
            'agency_name' => ['required', 'string', 'max:180'],
            'contact_name' => ['required', 'string', 'max:120'],
            'contact_email' => ['required', 'email:rfc', 'max:180'],
            'contact_phone' => ['nullable', 'string', 'max:60'],
            'contact_role' => ['nullable', 'string', 'max:120'],
            'deployment_plan' => ['required', Rule::in(array_keys(Country::deploymentPlanLabels()))],
            'deployment_type' => ['required', Rule::in(array_keys(Country::deploymentTypeLabels()))],
            'expected_posts' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'expected_users' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'modules' => ['nullable', 'array', 'max:4'],
            'modules.*' => ['string', Rule::in(['immigration', 'customs', 'health', 'security'])],
            'message' => ['nullable', 'string', 'max:3000'],
            'website' => ['nullable', 'string', 'max:255'],
        ];
    }
}
