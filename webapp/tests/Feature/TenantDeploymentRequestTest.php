<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\DeploymentRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantDeploymentRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_country_tenant_settings(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);

        $this->actingAs($admin)->post('/admin/countries/LBR', [
            'name' => 'Liberia',
            'tenant_slug' => 'liberia-immigration',
            'tenant_status' => Country::TENANT_STATUS_IMPLEMENTATION,
            'deployment_plan' => Country::PLAN_NATIONAL,
            'deployment_type' => Country::DEPLOYMENT_PRIVATE_CLOUD,
            'support_tier' => 'priority',
            'data_region' => 'West Africa',
            'primary_domain' => 'borderreach.example.gov.lr',
            'immigration_agency' => 'Liberia Immigration Service',
            'app_title' => 'Liberia BorderReach',
            'app_subtitle' => 'Liberia Immigration Service',
            'timezone' => 'Africa/Monrovia',
            'is_active' => '1',
        ])->assertRedirect(route('admin.countries.index'));

        $this->assertDatabaseHas('countries', [
            'code' => 'LBR',
            'tenant_slug' => 'liberia-immigration',
            'tenant_status' => Country::TENANT_STATUS_IMPLEMENTATION,
            'deployment_plan' => Country::PLAN_NATIONAL,
            'deployment_type' => Country::DEPLOYMENT_PRIVATE_CLOUD,
            'support_tier' => 'priority',
            'data_region' => 'West Africa',
            'primary_domain' => 'borderreach.example.gov.lr',
        ]);
    }

    public function test_public_deployment_request_can_be_submitted(): void
    {
        $this->post(route('deployment-requests.store'), [
            'country_name' => 'Ghana',
            'agency_name' => 'Ghana Immigration Service',
            'contact_name' => 'Ama Mensah',
            'contact_email' => 'ama.mensah@example.gov.gh',
            'contact_phone' => '+233000000000',
            'contact_role' => 'Director of Operations',
            'deployment_plan' => Country::PLAN_PROGRAM,
            'deployment_type' => Country::DEPLOYMENT_HOSTED,
            'expected_posts' => 42,
            'expected_users' => 800,
            'modules' => ['immigration', 'customs', 'security'],
            'message' => 'We want to pilot offline border reporting.',
        ])->assertRedirect(url('/#deployment'));

        $this->assertDatabaseHas('deployment_requests', [
            'country_name' => 'Ghana',
            'agency_name' => 'Ghana Immigration Service',
            'contact_email' => 'ama.mensah@example.gov.gh',
            'deployment_plan' => Country::PLAN_PROGRAM,
            'deployment_type' => Country::DEPLOYMENT_HOSTED,
            'status' => DeploymentRequest::STATUS_NEW,
        ]);

        $this->assertSame(
            ['immigration', 'customs', 'security'],
            DeploymentRequest::query()->where('country_name', 'Ghana')->firstOrFail()->modules
        );
    }

    public function test_get_started_deployment_request_returns_to_choice_page(): void
    {
        $this->post(route('deployment-requests.store'), [
            'return_to' => 'get-started',
            'country_name' => 'Liberia',
            'agency_name' => 'Liberia Immigration Service',
            'contact_name' => 'Musu Johnson',
            'contact_email' => 'musu.johnson@example.gov.lr',
            'deployment_plan' => Country::PLAN_NATIONAL,
            'deployment_type' => Country::DEPLOYMENT_PRIVATE_CLOUD,
            'modules' => ['immigration', 'customs'],
        ])->assertRedirect(route('get-started').'#deployment-request');

        $this->assertDatabaseHas('deployment_requests', [
            'country_name' => 'Liberia',
            'agency_name' => 'Liberia Immigration Service',
            'deployment_plan' => Country::PLAN_NATIONAL,
            'deployment_type' => Country::DEPLOYMENT_PRIVATE_CLOUD,
        ]);
    }

    public function test_admin_can_review_and_update_deployment_requests(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $deploymentRequest = DeploymentRequest::query()->create([
            'country_name' => 'The Gambia',
            'agency_name' => 'Gambia Immigration Department',
            'contact_name' => 'Lamin Jallow',
            'contact_email' => 'lamin.jallow@example.gm',
            'deployment_plan' => Country::PLAN_EVALUATION,
            'deployment_type' => Country::DEPLOYMENT_HYBRID,
            'modules' => ['immigration'],
            'status' => DeploymentRequest::STATUS_NEW,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.deployment-requests.index'))
            ->assertOk()
            ->assertSee('The Gambia')
            ->assertSee('Gambia Immigration Department');

        $this->actingAs($admin)
            ->post(route('admin.deployment-requests.update', $deploymentRequest), [
                'status' => DeploymentRequest::STATUS_CONTACTED,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('deployment_requests', [
            'id' => $deploymentRequest->id,
            'status' => DeploymentRequest::STATUS_CONTACTED,
        ]);
    }

    public function test_deployment_request_honeypot_is_not_stored(): void
    {
        $this->post(route('deployment-requests.store'), [
            'country_name' => 'Botland',
            'agency_name' => 'Spam Agency',
            'contact_name' => 'Automated Bot',
            'contact_email' => 'bot@example.test',
            'deployment_plan' => Country::PLAN_PROGRAM,
            'deployment_type' => Country::DEPLOYMENT_HOSTED,
            'website' => 'https://spam.example',
        ])->assertRedirect(url('/#deployment'));

        $this->assertDatabaseMissing('deployment_requests', [
            'country_name' => 'Botland',
        ]);
    }
}
