<?php

namespace Tests\Feature;

use App\Models\BorderPost;
use App\Models\DynamicForm;
use App\Models\MobileSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebFormCollectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_officer_can_submit_published_form_from_browser(): void
    {
        $post = BorderPost::query()->create([
            'country_code' => 'SLE',
            'code' => 'WEB_POST',
            'digital_address' => 'SLE-BP-WEB-POST',
            'name' => 'Web Collection Post',
            'region' => 'Kambia',
            'is_active' => true,
        ]);

        $officer = User::factory()->create([
            'border_post_id' => $post->id,
            'country_code' => 'SLE',
            'role' => 'border_officer',
            'is_active' => true,
        ]);

        $form = DynamicForm::query()->create([
            'country_code' => 'SLE',
            'form_id' => 'web_collection_test',
            'reporting_module' => DynamicForm::MODULE_IMMIGRATION,
            'title' => 'Web Collection Test',
            'is_template' => false,
        ]);

        $version = $form->versions()->create([
            'version' => 1,
            'source_file_path' => 'builder://web-collection-test',
            'compiled_schema' => [
                'formId' => 'web_collection_test',
                'version' => 1,
                'title' => 'Web Collection Test',
                'reportingModule' => DynamicForm::MODULE_IMMIGRATION,
                'fields' => [
                    ['id' => 'movement_type', 'type' => 'select_one', 'label' => 'Movement Type', 'required' => true, 'options' => [
                        ['value' => 'entry', 'label' => 'Entry'],
                        ['value' => 'exit', 'label' => 'Exit'],
                    ]],
                    ['id' => 'passport_number', 'type' => 'text', 'label' => 'Passport Number', 'required' => true],
                    ['id' => 'movement_date', 'type' => 'date', 'label' => 'Movement Date', 'required' => true],
                ],
                'choiceLists' => [],
            ],
            'source_metadata' => [],
            'is_published' => true,
        ]);
        $form->forceFill(['published_version_id' => $version->id])->save();

        $this->actingAs($officer)
            ->get(route('collect.forms.show', $form))
            ->assertOk()
            ->assertSee('Browser collection')
            ->assertSee('Passport Number');

        $this->actingAs($officer)
            ->post(route('collect.forms.store', $form), [
                'device_latitude' => '8.4928000',
                'device_longitude' => '-13.2351000',
                'device_location_accuracy_meters' => '14.5',
                'answers' => [
                    'movement_type' => 'entry',
                    'passport_number' => 'P1234567',
                    'movement_date' => '2026-06-22',
                ],
            ])
            ->assertRedirect(route('collect.forms.show', $form));

        $submission = MobileSubmission::query()->firstOrFail();

        $this->assertSame($officer->id, $submission->user_id);
        $this->assertSame($post->id, $submission->border_post_id);
        $this->assertSame('web_collection_test', $submission->form_id);
        $this->assertSame('SLE-BP-WEB-POST', $submission->border_post_digital_address);
        $this->assertSame('entry', $submission->answers['movement_type']);
        $this->assertSame('P1234567', $submission->answers['passport_number']);
        $this->assertSame('8.4928000', (string) $submission->device_latitude);
    }
}
