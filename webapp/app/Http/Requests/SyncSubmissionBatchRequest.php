<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'deviceId' => ['required', 'string', 'max:120', 'regex:/^[A-Za-z0-9._:-]+$/'],
            // Cap batch size and JSON length to protect the API from accidental or malicious huge payloads.
            'submissions' => ['required', 'array', 'min:1', 'max:100'],
            'submissions.*.localId' => ['required', 'string', 'max:120', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'submissions.*.formId' => ['required', 'string', 'max:120', 'regex:/^[A-Za-z0-9._-]+$/'],
            'submissions.*.formVersion' => ['required', 'integer', 'min:1'],
            'submissions.*.answersJson' => ['required', 'json', 'max:65535'],
            'submissions.*.createdAt' => ['nullable', 'integer', 'min:0'],
            'submissions.*.updatedAt' => ['nullable', 'integer', 'min:0'],
            'submissions.*.clientSyncAttemptedAt' => ['nullable', 'integer', 'min:0'],
            'submissions.*.deviceLatitude' => ['nullable', 'required_with:submissions.*.deviceLongitude', 'numeric', 'between:-90,90'],
            'submissions.*.deviceLongitude' => ['nullable', 'required_with:submissions.*.deviceLatitude', 'numeric', 'between:-180,180'],
            'submissions.*.deviceLocationAccuracyMeters' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'submissions.*.deviceLocationCapturedAt' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
