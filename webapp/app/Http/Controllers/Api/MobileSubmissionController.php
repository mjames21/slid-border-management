<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SyncSubmissionBatchRequest;
use App\Models\DynamicForm;
use App\Models\MobileDevice;
use App\Models\MobileSubmission;
use App\Services\AuditLogger;
use App\Services\MobileSubmissionValidator;
use App\Services\SubmissionWebhookDispatcher;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class MobileSubmissionController extends Controller
{
    public function __invoke(
        SyncSubmissionBatchRequest $request,
        MobileSubmissionValidator $validator,
        AuditLogger $audit,
        SubmissionWebhookDispatcher $webhooks
    ): JsonResponse {
        $user = $request->user()?->load('borderPost');
        $deviceId = $request->validated('deviceId');
        $device = MobileDevice::query()
            ->where('device_id', $deviceId)
            ->where('user_id', $user->id)
            ->first();

        if (! $user->canUseMobileApp() || ! $device || $device->isBlocked()) {
            $audit->record('mobile.sync_blocked', $user, $user->borderPost, $device, [
                'deviceId' => $deviceId,
                'reason' => $device ? 'inactive_or_blocked' : 'unknown_device',
            ], $request);

            return response()->json(['message' => 'This device is not authorized to sync submissions.'], 403);
        }

        $device->forceFill([
            'border_post_id' => $user->border_post_id,
            'country_code' => $user->operationalCountryCode(),
            'last_seen_at' => now(),
        ])->save();

        $acceptedIds = [];
        $accepted = [];
        $rejected = [];

        foreach ($request->validated('submissions') as $submission) {
            $form = DynamicForm::query()
                ->where('form_id', $submission['formId'])
                ->where('is_template', false)
                ->first();
            if (! $form || $form->country_code !== $user->operationalCountryCode()) {
                $rejected[] = ['localId' => $submission['localId'], 'reason' => 'Unknown form for this country assignment.'];

                continue;
            }

            $version = $form?->versions()
                ->where('version', $submission['formVersion'])
                ->where('is_published', true)
                ->first();

            if (! $version) {
                $rejected[] = ['localId' => $submission['localId'], 'reason' => 'Unknown or unpublished form version.'];

                continue;
            }

            $answers = json_decode($submission['answersJson'], true);
            if (! is_array($answers)) {
                $rejected[] = ['localId' => $submission['localId'], 'reason' => 'Invalid answers JSON.'];

                continue;
            }

            $errors = $validator->validate($version, $answers);
            if ($errors) {
                $rejected[] = ['localId' => $submission['localId'], 'reason' => implode(' ', $errors)];

                continue;
            }

            $existingSubmission = MobileSubmission::query()
                ->where('device_id', $deviceId)
                ->where('local_id', $submission['localId'])
                ->first();

            $storedSubmission = MobileSubmission::query()->updateOrCreate(
                ['device_id' => $deviceId, 'local_id' => $submission['localId']],
                [
                    'server_uid' => $existingSubmission?->server_uid ?: (string) Str::uuid(),
                    'user_id' => $user->id,
                    'mobile_device_id' => $device->id,
                    'border_post_id' => $user->border_post_id,
                    'country_code' => $user->operationalCountryCode(),
                    'border_post_code' => $user->borderPost?->code,
                    'border_post_digital_address' => $user->borderPost?->digital_address,
                    'region' => $user->borderPost?->region,
                    'reporting_module' => DynamicForm::normalizeModule($form->reporting_module),
                    'device_latitude' => $submission['deviceLatitude'] ?? null,
                    'device_longitude' => $submission['deviceLongitude'] ?? null,
                    'device_location_accuracy_meters' => $submission['deviceLocationAccuracyMeters'] ?? null,
                    'device_location_captured_at' => $this->millisToCarbon($submission['deviceLocationCapturedAt'] ?? null),
                    'form_id' => $submission['formId'],
                    'form_version' => $submission['formVersion'],
                    'answers' => $answers,
                    'client_created_at' => $this->millisToCarbon($submission['createdAt'] ?? null),
                    'client_updated_at' => $this->millisToCarbon($submission['updatedAt'] ?? null),
                    'client_synced_at' => $this->millisToCarbon($submission['clientSyncAttemptedAt'] ?? null),
                    'received_at' => now(),
                    'status' => 'accepted',
                    'rejection_reason' => null,
                    'source_ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]
            );

            $acceptedIds[] = $submission['localId'];
            $accepted[] = [
                'localId' => $submission['localId'],
                'serverId' => $storedSubmission->server_uid,
                'receivedAt' => optional($storedSubmission->received_at)->toIso8601String(),
            ];

            if ($storedSubmission->wasRecentlyCreated) {
                $webhooks->queueFor($storedSubmission);
            }
        }

        $audit->record('mobile.submissions_synced', $user, $user->borderPost, $device, [
            'accepted' => count($acceptedIds),
            'rejected' => count($rejected),
        ], $request);

        return response()->json([
            'acceptedIds' => $acceptedIds,
            'accepted' => $accepted,
            'rejected' => $rejected,
        ]);
    }

    private function millisToCarbon(?int $millis): ?CarbonImmutable
    {
        return $millis ? CarbonImmutable::createFromTimestampMs($millis) : null;
    }
}
