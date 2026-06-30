<?php

namespace App\Http\Requests;

use App\Models\DynamicForm;
use App\Services\WebhookEndpointGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOutboundWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin ?? false;
    }

    public function rules(): array
    {
        return [
            'country_code' => ['required', 'string', 'size:3', 'exists:countries,code'],
            'name' => ['required', 'string', 'max:120'],
            'endpoint_url' => ['required', 'url', 'max:2048'],
            'signing_secret' => ['nullable', 'string', 'min:16', 'max:255'],
            'reporting_module' => ['nullable', Rule::in(array_keys(DynamicForm::moduleLabels()))],
            'form_id' => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z0-9._-]+$/'],
            'timeout_seconds' => ['required', 'integer', 'min:2', 'max:30'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->has('endpoint_url')) {
                return;
            }

            $message = app(WebhookEndpointGuard::class)->validate((string) $this->input('endpoint_url'));
            if ($message) {
                $validator->errors()->add('endpoint_url', $message);
            }
        });
    }
}
