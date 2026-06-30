<?php

namespace App\Services;

use App\Models\WebhookDelivery;

class WebhookPayloadFactory
{
    public function make(WebhookDelivery $delivery): array
    {
        $submission = $delivery->mobileSubmission;
        $borderPost = $submission->borderPost;
        $device = $submission->mobileDevice;
        $officer = $submission->user;

        return [
            'event' => $delivery->event_type,
            'eventId' => (string) $delivery->id,
            'occurredAt' => optional($submission->received_at)->toIso8601String(),
            'country' => [
                'code' => $submission->country_code,
            ],
            'form' => [
                'id' => $submission->form_id,
                'version' => $submission->form_version,
                'reportingModule' => $submission->reporting_module,
            ],
            'submission' => [
                'serverUid' => $submission->server_uid,
                'localId' => $submission->local_id,
                'status' => $submission->status,
                'receivedAt' => optional($submission->received_at)->toIso8601String(),
                'answers' => is_array($submission->answers) ? $submission->answers : [],
            ],
            'borderPost' => [
                'id' => $borderPost?->id,
                'code' => $submission->border_post_code ?: $borderPost?->code,
                'digitalAddress' => $submission->border_post_digital_address ?: $borderPost?->digital_address,
                'region' => $submission->region ?: $borderPost?->region,
            ],
            'device' => [
                'id' => $submission->device_id,
                'registeredId' => $device?->id,
                'latitude' => $submission->device_latitude !== null ? (float) $submission->device_latitude : null,
                'longitude' => $submission->device_longitude !== null ? (float) $submission->device_longitude : null,
                'accuracyMeters' => $submission->device_location_accuracy_meters !== null
                    ? (float) $submission->device_location_accuracy_meters
                    : null,
                'locationCapturedAt' => optional($submission->device_location_captured_at)->toIso8601String(),
            ],
            'officer' => [
                'id' => $officer?->id,
                'name' => $officer?->name,
                'email' => $officer?->email,
            ],
        ];
    }
}
