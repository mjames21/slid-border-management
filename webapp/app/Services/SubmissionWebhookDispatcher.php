<?php

namespace App\Services;

use App\Jobs\DeliverSubmissionWebhook;
use App\Models\MobileSubmission;
use App\Models\OutboundWebhook;
use App\Models\WebhookDelivery;
use Illuminate\Database\QueryException;

class SubmissionWebhookDispatcher
{
    public function queueFor(MobileSubmission $submission): void
    {
        $webhooks = OutboundWebhook::query()
            ->where('is_active', true)
            ->where('country_code', $submission->country_code)
            ->where(function ($query) use ($submission) {
                $query->whereNull('reporting_module')
                    ->orWhere('reporting_module', $submission->reporting_module);
            })
            ->where(function ($query) use ($submission) {
                $query->whereNull('form_id')
                    ->orWhere('form_id', $submission->form_id);
            })
            ->get();

        foreach ($webhooks as $webhook) {
            $alreadyQueued = WebhookDelivery::query()
                ->where('outbound_webhook_id', $webhook->id)
                ->where('mobile_submission_id', $submission->id)
                ->exists();

            if ($alreadyQueued) {
                continue;
            }

            try {
                $delivery = WebhookDelivery::query()->create([
                    'outbound_webhook_id' => $webhook->id,
                    'mobile_submission_id' => $submission->id,
                    'event_type' => 'submission.accepted',
                    'status' => WebhookDelivery::STATUS_PENDING,
                ]);
            } catch (QueryException) {
                continue;
            }

            DeliverSubmissionWebhook::dispatch($delivery)->afterResponse();
        }
    }
}
