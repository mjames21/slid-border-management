<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class SyncSubmissionBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Token abilities let us reject a valid token if it was not issued for syncing data.
        return $this->user()?->tokenCan('mobile:sync') ?? false;
    }

    public function rules(): array
    {
        return [
            'deviceId' => ['required', 'string', 'max:160', 'not_regex:/[\/\\\\]/'],
            // Cap batch size and JSON length to protect the API from accidental or malicious huge payloads.
            'submissions' => ['required', 'array', 'min:1', 'max:100'],
            'submissions.*.localId' => ['required', 'string', 'max:160', 'not_regex:/[\/\\\\]/'],
            'submissions.*.formId' => ['required', 'string', 'max:120', 'regex:/^[A-Za-z0-9._-]+$/'],
            'submissions.*.formVersion' => ['required', 'integer', 'min:1'],
            'submissions.*.answersJson' => ['required', 'json', 'max:1048576'],
            'submissions.*.createdAt' => ['nullable', 'integer', 'min:0'],
            'submissions.*.updatedAt' => ['nullable', 'integer', 'min:0'],
            'submissions.*.clientSyncAttemptedAt' => ['nullable', 'integer', 'min:0'],
            'submissions.*.deviceLatitude' => ['nullable', 'numeric', 'between:-90,90'],
            'submissions.*.deviceLongitude' => ['nullable', 'numeric', 'between:-180,180'],
            'submissions.*.deviceLocationAccuracyMeters' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'submissions.*.deviceLocationCapturedAt' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ($this->input('submissions', []) as $index => $submission) {
                $hasLatitude = array_key_exists('deviceLatitude', $submission) && $submission['deviceLatitude'] !== null;
                $hasLongitude = array_key_exists('deviceLongitude', $submission) && $submission['deviceLongitude'] !== null;

                if ($hasLatitude xor $hasLongitude) {
                    $validator->errors()->add(
                        "submissions.{$index}.deviceLatitude",
                        'Device latitude and longitude must be sent together when GPS is available.'
                    );
                }
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        $firstError = collect($validator->errors()->all())->first()
            ?: 'Submission batch validation failed.';

        throw new HttpResponseException(response()->json([
            'message' => $firstError,
            'errors' => $validator->errors(),
        ], 422));
    }
}
