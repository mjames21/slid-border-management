<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Services\WebhookEndpointGuard;
use App\Services\WebhookPayloadFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Throwable;

class DeliverSubmissionWebhook implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(public WebhookDelivery $delivery) {}

    public function handle(WebhookPayloadFactory $payloadFactory, WebhookEndpointGuard $endpointGuard): void
    {
        $delivery = $this->delivery->fresh();
        if (! $delivery) {
            return;
        }

        $delivery->loadMissing([
            'outboundWebhook',
            'mobileSubmission.borderPost',
            'mobileSubmission.mobileDevice',
            'mobileSubmission.user',
        ]);

        $webhook = $delivery->outboundWebhook;
        $submission = $delivery->mobileSubmission;

        if (! $webhook || ! $submission || ! $webhook->appliesTo($submission)) {
            $this->markFailed($delivery, null, 'Webhook is inactive or no longer matches this submission.');

            return;
        }

        $endpointError = $endpointGuard->validate($webhook->endpoint_url);
        if ($endpointError) {
            $this->markFailed($delivery, null, $endpointError);

            return;
        }

        $payload = $payloadFactory->make($delivery);
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (! is_string($json)) {
            $this->markFailed($delivery, null, 'Webhook payload could not be encoded as JSON.');

            return;
        }

        $timestamp = (string) now()->unix();
        $signature = hash_hmac('sha256', $timestamp.'.'.$json, (string) $webhook->signing_secret);

        $delivery->forceFill([
            'status' => WebhookDelivery::STATUS_PENDING,
            'attempts' => $delivery->attempts + 1,
            'payload_sha256' => hash('sha256', $json),
            'last_attempted_at' => now(),
            'error_message' => null,
        ])->save();

        try {
            $response = Http::timeout($webhook->timeout_seconds)
                ->acceptJson()
                ->withHeaders([
                    'User-Agent' => 'BorderReach-Webhook/1.0',
                    'X-BorderReach-Event' => $delivery->event_type,
                    'X-BorderReach-Delivery' => (string) $delivery->id,
                    'X-BorderReach-Timestamp' => $timestamp,
                    'X-BorderReach-Signature' => 'sha256='.$signature,
                ])
                ->withBody($json, 'application/json')
                ->post($webhook->endpoint_url);

            if ($response->successful()) {
                $delivery->forceFill([
                    'status' => WebhookDelivery::STATUS_SUCCEEDED,
                    'last_status_code' => $response->status(),
                    'response_body' => $this->truncate($response->body()),
                    'error_message' => null,
                    'delivered_at' => now(),
                ])->save();

                return;
            }

            $this->markFailed(
                $delivery,
                $response->status(),
                'Endpoint returned HTTP '.$response->status().'.',
                $response->body()
            );
        } catch (Throwable $exception) {
            $this->markFailed($delivery, null, $exception->getMessage());
        }
    }

    private function markFailed(WebhookDelivery $delivery, ?int $statusCode, string $message, ?string $responseBody = null): void
    {
        $delivery->forceFill([
            'status' => WebhookDelivery::STATUS_FAILED,
            'last_status_code' => $statusCode,
            'response_body' => $responseBody !== null ? $this->truncate($responseBody) : $delivery->response_body,
            'error_message' => $this->truncate($message),
            'last_attempted_at' => now(),
        ])->save();
    }

    private function truncate(string $value): string
    {
        return mb_substr($value, 0, 2000);
    }
}
