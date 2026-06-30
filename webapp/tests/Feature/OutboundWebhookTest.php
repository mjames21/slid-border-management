<?php

namespace Tests\Feature;

use App\Jobs\DeliverSubmissionWebhook;
use App\Models\BorderPost;
use App\Models\DynamicForm;
use App\Models\MobileDevice;
use App\Models\MobileSubmission;
use App\Models\OutboundWebhook;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Services\WebhookEndpointGuard;
use App\Services\WebhookPayloadFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OutboundWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_rest_service_and_rejects_loopback_endpoint(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);

        $this->actingAs($admin)
            ->post(route('admin.webhooks.store'), [
                'country_code' => 'SLE',
                'name' => 'National Data Warehouse',
                'endpoint_url' => 'https://receiver.example.gov/borderreach/submissions',
                'signing_secret' => 'a-strong-webhook-secret',
                'reporting_module' => DynamicForm::MODULE_IMMIGRATION,
                'form_id' => '',
                'timeout_seconds' => 10,
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.webhooks.index'));

        $webhook = OutboundWebhook::query()->firstOrFail();

        $this->assertSame('National Data Warehouse', $webhook->name);
        $this->assertSame('SLE', $webhook->country_code);
        $this->assertSame('a-strong-webhook-secret', $webhook->signing_secret);
        $this->assertTrue($webhook->is_active);

        $this->actingAs($admin)
            ->from(route('admin.webhooks.index'))
            ->post(route('admin.webhooks.store'), [
                'country_code' => 'SLE',
                'name' => 'Unsafe Local Receiver',
                'endpoint_url' => 'http://127.0.0.1:9000/hook',
                'signing_secret' => 'another-strong-secret',
                'timeout_seconds' => 10,
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.webhooks.index'))
            ->assertSessionHasErrors('endpoint_url');

        $this->assertDatabaseMissing('outbound_webhooks', [
            'name' => 'Unsafe Local Receiver',
        ]);
    }

    public function test_accepted_mobile_submission_creates_one_webhook_delivery_on_retry(): void
    {
        Queue::fake();

        [$user, $device] = $this->mobileSyncFixture();

        OutboundWebhook::query()->create([
            'country_code' => 'SLE',
            'name' => 'Immigration Integration',
            'endpoint_url' => 'https://receiver.example.gov/hook',
            'signing_secret' => 'mobile-sync-webhook-secret',
            'reporting_module' => DynamicForm::MODULE_IMMIGRATION,
            'form_id' => 'entry_report',
            'is_active' => true,
            'timeout_seconds' => 10,
        ]);

        Sanctum::actingAs($user, ['mobile:sync']);

        $payload = [
            'deviceId' => $device->device_id,
            'submissions' => [[
                'localId' => 'offline-001',
                'formId' => 'entry_report',
                'formVersion' => 1,
                'answersJson' => json_encode(['traveller_name' => 'Aminata Conteh']),
                'createdAt' => 1_718_000_000_000,
                'updatedAt' => 1_718_000_000_000,
                'clientSyncAttemptedAt' => 1_718_000_001_000,
                'deviceLatitude' => 9.7401000,
                'deviceLongitude' => -11.6502000,
                'deviceLocationAccuracyMeters' => 12.5,
                'deviceLocationCapturedAt' => 1_718_000_000_500,
            ]],
        ];

        $this->postJson('/api/mobile/submissions/batch', $payload)->assertOk();
        $this->postJson('/api/mobile/submissions/batch', $payload)->assertOk();

        $this->assertDatabaseCount('webhook_deliveries', 1);
        $this->assertDatabaseHas('webhook_deliveries', [
            'event_type' => 'submission.accepted',
            'status' => WebhookDelivery::STATUS_PENDING,
        ]);
        Queue::assertPushed(DeliverSubmissionWebhook::class);
    }

    public function test_webhook_delivery_job_posts_signed_submission_payload(): void
    {
        Http::fake([
            'https://receiver.test/*' => Http::response(['ok' => true], 202),
        ]);

        [$submission, $webhook, $secret] = $this->deliveryFixture();
        $delivery = WebhookDelivery::query()->create([
            'outbound_webhook_id' => $webhook->id,
            'mobile_submission_id' => $submission->id,
            'event_type' => 'submission.accepted',
            'status' => WebhookDelivery::STATUS_PENDING,
        ]);

        (new DeliverSubmissionWebhook($delivery))->handle(
            app(WebhookPayloadFactory::class),
            app(WebhookEndpointGuard::class)
        );

        Http::assertSent(function (Request $request) use ($secret) {
            $timestamp = $request->header('X-BorderReach-Timestamp')[0] ?? '';
            $signature = $request->header('X-BorderReach-Signature')[0] ?? '';
            $payload = json_decode($request->body(), true);
            $expected = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$request->body(), $secret);

            return $request->url() === 'https://receiver.test/hooks/borderreach'
                && $request->method() === 'POST'
                && ($request->header('X-BorderReach-Event')[0] ?? '') === 'submission.accepted'
                && hash_equals($expected, $signature)
                && ($payload['submission']['answers']['traveller_name'] ?? null) === 'Aminata Conteh'
                && ($payload['borderPost']['digitalAddress'] ?? null) === 'SLE-BP-FAL-TEST'
                && ($payload['device']['latitude'] ?? null) === 9.7401;
        });

        $this->assertDatabaseHas('webhook_deliveries', [
            'id' => $delivery->id,
            'status' => WebhookDelivery::STATUS_SUCCEEDED,
            'last_status_code' => 202,
            'attempts' => 1,
        ]);
    }

    private function mobileSyncFixture(): array
    {
        $post = BorderPost::query()->create([
            'country_code' => 'SLE',
            'code' => 'FAL-WEBHOOK',
            'digital_address' => 'SLE-BP-FAL-WEBHOOK',
            'name' => 'Falaba Webhook Test',
            'region' => 'Falaba',
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'border_post_id' => $post->id,
            'country_code' => 'SLE',
            'role' => 'border_officer',
            'is_active' => true,
        ]);

        $device = MobileDevice::query()->create([
            'user_id' => $user->id,
            'border_post_id' => $post->id,
            'country_code' => 'SLE',
            'device_id' => 'android-webhook-device',
            'name' => 'android-webhook-device',
        ]);

        $form = DynamicForm::query()->create([
            'country_code' => 'SLE',
            'form_id' => 'entry_report',
            'reporting_module' => DynamicForm::MODULE_IMMIGRATION,
            'title' => 'Entry Report',
        ]);
        $version = $form->versions()->create([
            'version' => 1,
            'source_file_path' => 'tests/entry-report.xlsx',
            'compiled_schema' => [
                'formId' => 'entry_report',
                'version' => 1,
                'title' => 'Entry Report',
                'reportingModule' => DynamicForm::MODULE_IMMIGRATION,
                'fields' => [
                    ['id' => 'traveller_name', 'type' => 'text', 'label' => 'Traveller Name', 'required' => true],
                ],
                'choiceLists' => [],
            ],
            'source_metadata' => [],
            'is_published' => true,
        ]);
        $form->forceFill(['published_version_id' => $version->id])->save();

        return [$user, $device];
    }

    private function deliveryFixture(): array
    {
        $post = BorderPost::query()->create([
            'country_code' => 'SLE',
            'code' => 'FAL-TEST',
            'digital_address' => 'SLE-BP-FAL-TEST',
            'name' => 'Falaba Test',
            'region' => 'Falaba',
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'border_post_id' => $post->id,
            'country_code' => 'SLE',
            'role' => 'border_officer',
            'is_active' => true,
        ]);

        $device = MobileDevice::query()->create([
            'user_id' => $user->id,
            'border_post_id' => $post->id,
            'country_code' => 'SLE',
            'device_id' => 'android-signed-device',
            'name' => 'android-signed-device',
        ]);

        $submission = MobileSubmission::query()->create([
            'server_uid' => 'server-submission-001',
            'user_id' => $user->id,
            'mobile_device_id' => $device->id,
            'border_post_id' => $post->id,
            'country_code' => 'SLE',
            'border_post_code' => $post->code,
            'border_post_digital_address' => $post->digital_address,
            'region' => $post->region,
            'reporting_module' => DynamicForm::MODULE_IMMIGRATION,
            'device_latitude' => 9.7401000,
            'device_longitude' => -11.6502000,
            'device_location_accuracy_meters' => 12.5,
            'device_location_captured_at' => now(),
            'device_id' => $device->device_id,
            'local_id' => 'offline-001',
            'form_id' => 'entry_report',
            'form_version' => 1,
            'answers' => ['traveller_name' => 'Aminata Conteh'],
            'client_created_at' => now(),
            'client_updated_at' => now(),
            'client_synced_at' => now(),
            'received_at' => now(),
            'status' => 'accepted',
        ]);

        $secret = 'signed-delivery-secret';
        $webhook = OutboundWebhook::query()->create([
            'country_code' => 'SLE',
            'name' => 'Signed Receiver',
            'endpoint_url' => 'https://receiver.test/hooks/borderreach',
            'signing_secret' => $secret,
            'reporting_module' => DynamicForm::MODULE_IMMIGRATION,
            'form_id' => 'entry_report',
            'is_active' => true,
            'timeout_seconds' => 10,
        ]);

        return [$submission, $webhook, $secret];
    }
}
