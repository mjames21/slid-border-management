<?php

namespace Tests\Feature;

use App\Models\BorderPost;
use App\Models\DynamicForm;
use App\Models\FrequentLocation;
use App\Models\MobileSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCountryScopedModulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_lists_filter_border_posts_users_forms_and_locations_by_country(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'is_active' => true,
            'role' => User::ROLE_PLATFORM_ADMIN,
        ]);

        $slePost = BorderPost::query()->create([
            'country_code' => 'SLE',
            'code' => 'FAL_SCOPE_TEST',
            'name' => 'Falaba Scope Test',
            'region' => 'Falaba',
            'is_active' => true,
        ]);
        $lbrPost = BorderPost::query()->create([
            'country_code' => 'LBR',
            'code' => 'BOW_SCOPE_TEST',
            'name' => 'Bo Waterside Scope Test',
            'region' => 'Grand Cape Mount',
            'is_active' => true,
        ]);

        User::factory()->create([
            'name' => 'Falaba Scoped Officer',
            'email' => 'falaba.scope@example.test',
            'country_code' => 'SLE',
            'border_post_id' => $slePost->id,
            'role' => 'border_officer',
            'is_active' => true,
        ]);
        User::factory()->create([
            'name' => 'Bo Waterside Scoped Officer',
            'email' => 'bow.scope@example.test',
            'country_code' => 'LBR',
            'border_post_id' => $lbrPost->id,
            'role' => 'border_officer',
            'is_active' => true,
        ]);

        DynamicForm::query()->create([
            'country_code' => 'SLE',
            'reporting_module' => DynamicForm::MODULE_IMMIGRATION,
            'form_id' => 'sle_scope_form',
            'title' => 'Sierra Leone Scope Form',
        ]);
        DynamicForm::query()->create([
            'country_code' => 'LBR',
            'reporting_module' => DynamicForm::MODULE_CUSTOMS,
            'form_id' => 'lbr_scope_form',
            'title' => 'Liberia Scope Form',
        ]);

        FrequentLocation::query()->create([
            'country_code' => 'SLE',
            'country_name' => 'Sierra Leone',
            'name' => 'Kambia Scope Location',
            'admin_area' => 'Kambia',
            'category' => 'town',
            'is_active' => true,
        ]);
        FrequentLocation::query()->create([
            'country_code' => 'LBR',
            'country_name' => 'Liberia',
            'name' => 'Bo Waterside Scope Location',
            'admin_area' => 'Grand Cape Mount',
            'category' => 'town',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get('/admin/border-posts?country_code=LBR')
            ->assertOk()
            ->assertSee('Bo Waterside Scope Test')
            ->assertDontSee('Falaba Scope Test');

        $this->actingAs($admin)
            ->get('/admin/users?country_code=LBR')
            ->assertOk()
            ->assertSee('Bo Waterside Scoped Officer')
            ->assertDontSee('Falaba Scoped Officer');

        $this->actingAs($admin)
            ->get('/admin/forms?country_code=LBR')
            ->assertOk()
            ->assertSee('Liberia Scope Form')
            ->assertDontSee('Sierra Leone Scope Form');

        $this->actingAs($admin)
            ->get('/admin/locations?country_code=LBR')
            ->assertOk()
            ->assertSee('Bo Waterside Scope Location')
            ->assertDontSee('Kambia Scope Location');
    }

    public function test_dynamic_form_ids_are_unique_inside_each_country(): void
    {
        DynamicForm::query()->create([
            'country_code' => 'SLE',
            'reporting_module' => DynamicForm::MODULE_IMMIGRATION,
            'form_id' => 'doc9303_inspection',
            'title' => 'Sierra Leone Inspection',
        ]);

        DynamicForm::query()->create([
            'country_code' => 'LBR',
            'reporting_module' => DynamicForm::MODULE_IMMIGRATION,
            'form_id' => 'doc9303_inspection',
            'title' => 'Liberia Inspection',
        ]);

        $this->assertDatabaseCount('dynamic_forms', 2);
    }

    public function test_submission_show_renders_schema_ordered_answer_table(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'is_active' => true,
            'country_code' => 'SLE',
            'role' => User::ROLE_HQ_ADMIN,
        ]);
        $form = DynamicForm::query()->create([
            'country_code' => 'SLE',
            'reporting_module' => DynamicForm::MODULE_IMMIGRATION,
            'form_id' => 'inspection_report',
            'title' => 'Inspection Report',
        ]);
        $version = $form->versions()->create([
            'version' => 1,
            'source_file_path' => 'tests/inspection.xlsx',
            'compiled_schema' => [
                'formId' => 'inspection_report',
                'version' => 1,
                'title' => 'Inspection Report',
                'fields' => [
                    ['id' => 'traveller_name', 'type' => 'text', 'label' => 'Traveller Name'],
                    [
                        'id' => 'movement_type',
                        'type' => 'select_one',
                        'label' => 'Movement Type',
                        'options' => [['value' => 'entry', 'label' => 'Entry']],
                    ],
                    [
                        'id' => 'risk_flags',
                        'type' => 'select_multiple',
                        'label' => 'Risk Flags',
                        'options' => [
                            ['value' => 'missing_visa', 'label' => 'Missing visa'],
                            ['value' => 'watchlist', 'label' => 'Watchlist'],
                        ],
                    ],
                ],
            ],
            'source_metadata' => [],
            'is_published' => true,
        ]);
        $form->forceFill(['published_version_id' => $version->id])->save();

        $submission = MobileSubmission::query()->create([
            'device_id' => 'answer-table-device',
            'local_id' => 'answer-table-1',
            'form_id' => 'inspection_report',
            'form_version' => 1,
            'answers' => [
                'traveller_name' => 'Aminata Conteh',
                'movement_type' => 'entry',
                'risk_flags' => ['missing_visa', 'watchlist'],
                'unexpected_note' => 'Manual note retained',
            ],
            'received_at' => now(),
            'country_code' => 'SLE',
            'reporting_module' => DynamicForm::MODULE_IMMIGRATION,
            'status' => 'accepted',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.submissions.show', $submission))
            ->assertOk()
            ->assertSee('Report Answers')
            ->assertSee('Traveller Name')
            ->assertSee('Aminata Conteh')
            ->assertSee('Entry')
            ->assertSee('Missing visa, Watchlist')
            ->assertSee('Unexpected Note')
            ->assertSee('Manual note retained')
            ->assertSee('Not found in form schema');
    }

    public function test_country_admin_is_scoped_to_own_workspace_even_when_url_requests_another_country(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'is_active' => true,
            'country_code' => 'SLE',
            'role' => User::ROLE_HQ_ADMIN,
        ]);

        DynamicForm::query()->create([
            'country_code' => 'SLE',
            'reporting_module' => DynamicForm::MODULE_IMMIGRATION,
            'form_id' => 'sle_only_form',
            'title' => 'Sierra Leone Only Form',
        ]);
        DynamicForm::query()->create([
            'country_code' => 'LBR',
            'reporting_module' => DynamicForm::MODULE_CUSTOMS,
            'form_id' => 'lbr_hidden_form',
            'title' => 'Liberia Hidden Form',
        ]);

        $this->actingAs($admin)
            ->get('/admin/forms?country_code=LBR')
            ->assertOk()
            ->assertSee('Sierra Leone Only Form')
            ->assertDontSee('Liberia Hidden Form');
    }

    public function test_country_admin_cannot_open_another_workspace_submission(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'is_active' => true,
            'country_code' => 'SLE',
            'role' => User::ROLE_HQ_ADMIN,
        ]);

        $submission = MobileSubmission::query()->create([
            'device_id' => 'lbr-device',
            'local_id' => 'lbr-submission-1',
            'form_id' => 'lbr_form',
            'form_version' => 1,
            'answers' => ['traveller_name' => 'Hidden Traveller'],
            'received_at' => now(),
            'country_code' => 'LBR',
            'reporting_module' => DynamicForm::MODULE_IMMIGRATION,
            'status' => 'accepted',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.submissions.show', $submission))
            ->assertForbidden();
    }
}
