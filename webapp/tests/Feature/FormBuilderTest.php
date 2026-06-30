<?php

namespace Tests\Feature;

use App\Models\DynamicForm;
use App\Models\User;
use App\Services\FormTemplateLibrary;
use Database\Seeders\FormTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FormBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_builder_opens_with_icao_doc_9303_starter_template(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);

        $this->actingAs($admin)
            ->get('/admin/forms/builder/new')
            ->assertOk()
            ->assertSee('Full ICAO Doc 9303 Inspection Template')
            ->assertSee('slid_icao_doc_9303_full_inspection')
            ->assertSee('MRZ Composite Check Digit')
            ->assertSee('Passive Authentication Result')
            ->assertSee('Health / Quarantine Screening');
    }

    public function test_standards_template_is_cloned_before_editing(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $this->seed(FormTemplateSeeder::class);

        $template = DynamicForm::query()
            ->where('form_id', 'border_customs_goods_declaration')
            ->firstOrFail();

        $this->assertTrue($template->is_template);

        $this->actingAs($admin)
            ->get(route('admin.forms.builder.edit', $template))
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('admin.forms.templates.clone', $template), [
                'title' => 'Kambia Customs Report',
                'country_code' => 'SLE',
            ])
            ->assertRedirect();

        $copy = DynamicForm::query()
            ->where('title', 'Kambia Customs Report')
            ->firstOrFail();

        $this->assertFalse($copy->is_template);
        $this->assertSame(DynamicForm::MODULE_CUSTOMS, $copy->reporting_module);
        $this->assertSame(1, $copy->versions()->count());
        $this->assertNull($copy->published_version_id);
        $this->assertSame('template_clone', $copy->versions()->first()->source_metadata['source']);
    }

    public function test_admin_can_publish_builder_form_and_mobile_sync_receives_schema(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);

        $this->actingAs($admin)
            ->post('/admin/forms/builder', [
                'form_id' => 'immigration.border.daily',
                'reporting_module' => DynamicForm::MODULE_IMMIGRATION,
                'title' => 'Immigration Border Daily',
                'publish' => '1',
                'fields' => [
                    [
                        'id' => 'movement_type',
                        'type' => 'select_one',
                        'label' => 'Movement Type',
                        'required' => '1',
                        'options' => "entry|Entry\nexit|Exit",
                    ],
                    [
                        'id' => 'passport_number',
                        'type' => 'text',
                        'label' => 'Passport Number',
                        'required' => '1',
                    ],
                ],
            ])
            ->assertRedirect();

        $form = DynamicForm::query()->where('form_id', 'immigration.border.daily')->firstOrFail();
        $version = $form->publishedVersion;

        $this->assertNotNull($version);
        $this->assertSame(DynamicForm::MODULE_IMMIGRATION, $form->reporting_module);
        $this->assertSame('form_builder', $version->source_metadata['source']);
        $this->assertSame('immigration.border.daily', $version->compiled_schema['formId']);
        $this->assertSame(DynamicForm::MODULE_IMMIGRATION, $version->compiled_schema['reportingModule']);
        $this->assertSame(DynamicForm::standardReferenceForModule(DynamicForm::MODULE_IMMIGRATION), $version->compiled_schema['standardReference']);
        $this->assertSame('builder://forms/'.$form->id.'/versions/1', $version->source_file_path);

        Sanctum::actingAs(User::factory()->create(), ['mobile:read']);

        $this->getJson('/api/mobile/config')
            ->assertOk()
            ->assertJsonPath('activeForms.0.formId', 'immigration.border.daily')
            ->assertJsonPath('activeForms.0.reportingModule', DynamicForm::MODULE_IMMIGRATION)
            ->assertJsonPath('activeForms.0.standardReference', DynamicForm::standardReferenceForModule(DynamicForm::MODULE_IMMIGRATION))
            ->assertJsonPath('activeForms.0.title', 'Immigration Border Daily')
            ->assertJsonPath('activeForms.0.fields.0.type', 'select_one')
            ->assertJsonPath('activeForms.0.choiceLists.movement_type_options.0.label', 'Entry');
    }

    public function test_full_doc_9303_template_can_be_published(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $template = app(FormTemplateLibrary::class)->get('icao_doc_9303_border_movement');

        $this->actingAs($admin)->post('/admin/forms/builder', [
            'form_id' => $template['form_id'],
            'reporting_module' => $template['reporting_module'],
            'title' => $template['title'],
            'publish' => '1',
            'fields' => $template['fields'],
        ])->assertRedirect();

        $schema = DynamicForm::query()
            ->where('form_id', $template['form_id'])
            ->firstOrFail()
            ->publishedVersion
            ->compiled_schema;

        $fieldIds = collect($schema['fields'])->pluck('id');

        $this->assertGreaterThan(60, $fieldIds->count());
        $this->assertTrue($fieldIds->contains('mrz_composite_check_digit'));
        $this->assertTrue($fieldIds->contains('passive_authentication_result'));
    }

    public function test_health_quarantine_template_can_be_published_for_mobile_sync(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $template = app(FormTemplateLibrary::class)->get('health_quarantine_screening');

        $this->actingAs($admin)->post('/admin/forms/builder', [
            'form_id' => $template['form_id'],
            'reporting_module' => $template['reporting_module'],
            'title' => $template['title'],
            'publish' => '1',
            'fields' => $template['fields'],
        ])->assertRedirect();

        $form = DynamicForm::query()->where('form_id', $template['form_id'])->firstOrFail();

        $this->assertSame(DynamicForm::MODULE_HEALTH, $form->reporting_module);
        $this->assertSame(DynamicForm::MODULE_HEALTH, $form->publishedVersion->compiled_schema['reportingModule']);
        $this->assertSame(DynamicForm::standardReferenceForModule(DynamicForm::MODULE_HEALTH), $form->publishedVersion->compiled_schema['standardReference']);
        $this->assertTrue(collect($form->publishedVersion->compiled_schema['fields'])->pluck('id')->contains('screening_result'));
        $this->assertTrue(collect($form->publishedVersion->compiled_schema['fields'])->pluck('id')->contains('health_authority_notified'));
    }

    public function test_single_window_templates_keep_international_standards_baselines(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $templates = app(FormTemplateLibrary::class);

        foreach ([
            'customs_goods_declaration' => [
                'module' => DynamicForm::MODULE_CUSTOMS,
                'required_fields' => ['customs_procedure', 'hs_code', 'duty_or_tax_assessed'],
            ],
            'security_incident_report' => [
                'module' => DynamicForm::MODULE_SECURITY,
                'required_fields' => ['incident_type', 'agency_notified', 'follow_up_required'],
            ],
        ] as $templateKey => $expectation) {
            $template = $templates->get($templateKey);

            $this->actingAs($admin)->post('/admin/forms/builder', [
                'form_id' => $template['form_id'],
                'reporting_module' => $template['reporting_module'],
                'title' => $template['title'],
                'publish' => '1',
                'fields' => $template['fields'],
            ])->assertRedirect();

            $schema = DynamicForm::query()
                ->where('form_id', $template['form_id'])
                ->firstOrFail()
                ->publishedVersion
                ->compiled_schema;
            $fieldIds = collect($schema['fields'])->pluck('id');

            $this->assertSame($expectation['module'], $schema['reportingModule']);
            $this->assertSame(DynamicForm::standardReferenceForModule($expectation['module']), $schema['standardReference']);

            foreach ($expectation['required_fields'] as $fieldId) {
                $this->assertTrue($fieldIds->contains($fieldId), "The {$templateKey} template should include {$fieldId}.");
            }
        }
    }

    public function test_unselected_builder_rows_are_not_compiled_into_mobile_schema(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);

        $this->actingAs($admin)->post('/admin/forms/builder', [
            'form_id' => 'selective.doc9303.form',
            'reporting_module' => DynamicForm::MODULE_IMMIGRATION,
            'title' => 'Selective Doc 9303 Form',
            'publish' => '1',
            'fields' => [
                [
                    'include' => '1',
                    'id' => 'document_number',
                    'type' => 'text',
                    'label' => 'Document Number',
                    'required' => '1',
                ],
                [
                    'include' => '0',
                    'id' => 'chip_authentication_result',
                    'type' => 'select_one',
                    'label' => 'Chip Authentication Result',
                    'options' => "passed|Passed\nfailed|Failed",
                ],
            ],
        ])->assertRedirect();

        $schema = DynamicForm::query()
            ->where('form_id', 'selective.doc9303.form')
            ->firstOrFail()
            ->publishedVersion
            ->compiled_schema;

        $fieldIds = collect($schema['fields'])->pluck('id')->all();

        $this->assertContains('document_number', $fieldIds);
        $this->assertNotContains('chip_authentication_result', $fieldIds);
    }

    public function test_editing_builder_form_creates_next_draft_version_without_changing_form_id(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);

        $this->actingAs($admin)->post('/admin/forms/builder', [
            'form_id' => 'stable.border.form',
            'reporting_module' => DynamicForm::MODULE_SECURITY,
            'title' => 'Stable Border Form',
            'publish' => '1',
            'fields' => [[
                'id' => 'full_name',
                'type' => 'text',
                'label' => 'Full Name',
                'required' => '1',
            ]],
        ]);

        $form = DynamicForm::query()->where('form_id', 'stable.border.form')->firstOrFail();

        $this->actingAs($admin)->post("/admin/forms/{$form->id}/builder", [
            'form_id' => 'changed.form.id',
            'reporting_module' => DynamicForm::MODULE_HEALTH,
            'title' => 'Stable Border Form Updated',
            'fields' => [[
                'id' => 'full_name',
                'type' => 'text',
                'label' => 'Full Name',
                'required' => '1',
            ]],
        ])->assertRedirect();

        $form->refresh();

        $this->assertSame('stable.border.form', $form->form_id);
        $this->assertSame(DynamicForm::MODULE_HEALTH, $form->reporting_module);
        $this->assertSame('Stable Border Form Updated', $form->title);
        $this->assertSame(2, $form->versions()->count());
        $this->assertSame(1, $form->publishedVersion->version);
    }
}
