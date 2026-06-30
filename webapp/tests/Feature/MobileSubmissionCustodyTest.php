<?php

namespace Tests\Feature;

use App\Models\BorderPost;
use App\Models\DynamicForm;
use App\Models\MobileDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileSubmissionCustodyTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_records_user_device_and_border_post_custody(): void
    {
        $post = BorderPost::query()->create([
            'country_code' => 'SLE',
            'code' => 'FAL_SYNC_TEST',
            'digital_address' => 'SLE-BP-FAL-SYNC-TEST',
            'name' => 'Falaba Sync Test',
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
            'device_id' => 'android-sync-device',
            'name' => 'android-sync-device',
        ]);

        $form = DynamicForm::query()->create([
            'form_id' => 'custody_form',
            'reporting_module' => DynamicForm::MODULE_SECURITY,
            'title' => 'Custody Form',
        ]);
        $version = $form->versions()->create([
            'version' => 1,
            'source_file_path' => 'tests/custody.xlsx',
            'compiled_schema' => [
                'formId' => 'custody_form',
                'version' => 1,
                'title' => 'Custody Form',
                'reportingModule' => DynamicForm::MODULE_SECURITY,
                'fields' => [
                    ['id' => 'full_name', 'type' => 'text', 'label' => 'Full Name', 'required' => true],
                ],
                'choiceLists' => [],
            ],
            'source_metadata' => [],
            'is_published' => true,
        ]);
        $form->forceFill(['published_version_id' => $version->id])->save();

        Sanctum::actingAs($user, ['mobile:sync']);

        $response = $this->postJson('/api/mobile/submissions/batch', [
            'deviceId' => $device->device_id,
            'submissions' => [[
                'localId' => 'local-1',
                'formId' => 'custody_form',
                'formVersion' => 1,
                'answersJson' => json_encode(['full_name' => 'Aminata Conteh']),
                'createdAt' => 1_718_000_000_000,
                'updatedAt' => 1_718_000_000_000,
                'clientSyncAttemptedAt' => 1_718_000_001_000,
                'deviceLatitude' => 9.7401000,
                'deviceLongitude' => -11.6502000,
                'deviceLocationAccuracyMeters' => 12.5,
                'deviceLocationCapturedAt' => 1_718_000_000_500,
            ]],
        ]);

        $response->assertOk()
            ->assertJsonPath('acceptedIds.0', 'local-1')
            ->assertJsonPath('accepted.0.localId', 'local-1');

        $this->assertNotEmpty($response->json('accepted.0.serverId'));
        $this->assertNotEmpty($response->json('accepted.0.receivedAt'));

        $this->assertDatabaseHas('mobile_submissions', [
            'local_id' => 'local-1',
            'user_id' => $user->id,
            'mobile_device_id' => $device->id,
            'border_post_id' => $post->id,
            'country_code' => 'SLE',
            'border_post_code' => 'FAL_SYNC_TEST',
            'border_post_digital_address' => 'SLE-BP-FAL-SYNC-TEST',
            'reporting_module' => DynamicForm::MODULE_SECURITY,
            'region' => 'Falaba',
            'device_latitude' => '9.7401000',
            'device_longitude' => '-11.6502000',
            'device_location_accuracy_meters' => '12.50',
        ]);

        $this->assertNotNull(\App\Models\MobileSubmission::query()->where('local_id', 'local-1')->value('server_uid'));
    }
}
