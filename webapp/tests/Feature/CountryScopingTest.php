<?php

namespace Tests\Feature;

use App\Models\BorderPost;
use App\Models\DynamicForm;
use App\Models\MobileDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CountryScopingTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_config_only_returns_forms_for_assigned_country(): void
    {
        $sleUser = $this->mobileUserForCountry('SLE', 'KAM_SLE_TEST', 'Kambia Sierra Leone Test');
        $lbrUser = $this->mobileUserForCountry('LBR', 'BOW_LBR_TEST', 'Bo Waterside Liberia Test');

        $this->publishForm('SLE', 'sle_doc9303', 'Sierra Leone Doc 9303');
        $this->publishForm('LBR', 'lbr_doc9303', 'Liberia Doc 9303');

        Sanctum::actingAs($sleUser, ['mobile:read']);
        $this->getJson('/api/mobile/config')
            ->assertOk()
            ->assertJsonPath('activeForms.0.formId', 'sle_doc9303')
            ->assertJsonPath('activeForms.0.reportingModule', DynamicForm::MODULE_IMMIGRATION)
            ->assertJsonMissing(['formId' => 'lbr_doc9303']);

        Sanctum::actingAs($lbrUser, ['mobile:read']);
        $this->getJson('/api/mobile/config')
            ->assertOk()
            ->assertJsonPath('activeForms.0.formId', 'lbr_doc9303')
            ->assertJsonMissing(['formId' => 'sle_doc9303']);
    }

    public function test_mobile_submission_is_stamped_with_operational_country(): void
    {
        $user = $this->mobileUserForCountry('LBR', 'BOW_LBR_SYNC', 'Bo Waterside Sync');
        $device = MobileDevice::query()->create([
            'user_id' => $user->id,
            'border_post_id' => $user->border_post_id,
            'country_code' => 'LBR',
            'device_id' => 'lbr-android-device',
            'name' => 'lbr-android-device',
        ]);
        $this->publishForm('LBR', 'lbr_movement', 'Liberia Movement', DynamicForm::MODULE_CUSTOMS);

        Sanctum::actingAs($user, ['mobile:sync']);

        $this->postJson('/api/mobile/submissions/batch', [
            'deviceId' => $device->device_id,
            'submissions' => [[
                'localId' => 'local-lbr-1',
                'formId' => 'lbr_movement',
                'formVersion' => 1,
                'answersJson' => json_encode(['document_number' => 'P1234567']),
                'createdAt' => 1_718_000_000_000,
                'updatedAt' => 1_718_000_000_000,
            ]],
        ])->assertOk()->assertJsonPath('acceptedIds.0', 'local-lbr-1');

        $this->assertDatabaseHas('mobile_submissions', [
            'local_id' => 'local-lbr-1',
            'country_code' => 'LBR',
            'border_post_code' => 'BOW_LBR_SYNC',
            'reporting_module' => DynamicForm::MODULE_CUSTOMS,
        ]);
    }

    private function mobileUserForCountry(string $countryCode, string $postCode, string $postName): User
    {
        $post = BorderPost::query()->create([
            'country_code' => $countryCode,
            'code' => $postCode,
            'name' => $postName,
            'region' => $countryCode === 'LBR' ? 'Grand Cape Mount' : 'Kambia',
            'is_active' => true,
        ]);

        return User::factory()->create([
            'country_code' => $countryCode,
            'border_post_id' => $post->id,
            'role' => 'border_officer',
            'is_active' => true,
        ]);
    }

    private function publishForm(string $countryCode, string $formId, string $title, string $reportingModule = DynamicForm::MODULE_IMMIGRATION): void
    {
        $form = DynamicForm::query()->create([
            'country_code' => $countryCode,
            'reporting_module' => $reportingModule,
            'form_id' => $formId,
            'title' => $title,
        ]);

        $version = $form->versions()->create([
            'version' => 1,
            'source_file_path' => "tests/{$formId}.xlsx",
            'compiled_schema' => [
                'formId' => $formId,
                'version' => 1,
                'title' => $title,
                'reportingModule' => $reportingModule,
                'fields' => [
                    ['id' => 'document_number', 'type' => 'text', 'label' => 'Document Number', 'required' => true],
                ],
                'choiceLists' => [],
            ],
            'source_metadata' => [],
            'is_published' => true,
        ]);

        $form->forceFill(['published_version_id' => $version->id])->save();
    }
}
