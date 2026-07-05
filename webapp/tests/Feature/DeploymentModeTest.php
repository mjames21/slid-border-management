<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DeploymentModeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        config([
            'borderreach.platform_mode' => true,
            'borderreach.tenant_country_code' => 'SLE',
        ]);

        parent::tearDown();
    }

    public function test_platform_mode_allows_public_deployment_request_pages(): void
    {
        config(['borderreach.platform_mode' => true]);

        $this->get('/')->assertOk();
        $this->get('/get-started')->assertOk();

        $platformAdmin = User::factory()->create([
            'is_admin' => true,
            'role' => User::ROLE_PLATFORM_ADMIN,
            'country_code' => 'SLE',
            'is_active' => true,
        ]);

        $this->assertTrue($platformAdmin->canManageAllTenants());
        $this->assertTrue($platformAdmin->canManageDeploymentRequests());
    }

    public function test_client_mode_redirects_public_workspace_pages_to_login(): void
    {
        config(['borderreach.platform_mode' => false]);

        $this->get('/')->assertRedirect(route('login'));
        $this->get('/get-started')->assertRedirect(route('login'));

        $this->post('/deployment-requests', [])->assertNotFound();
    }

    public function test_client_mode_disables_effective_platform_admin_access(): void
    {
        config([
            'borderreach.platform_mode' => false,
            'borderreach.tenant_country_code' => 'SLE',
        ]);

        $user = User::factory()->create([
            'is_admin' => true,
            'role' => User::ROLE_PLATFORM_ADMIN,
            'country_code' => 'SLE',
            'is_active' => true,
        ]);

        $this->assertFalse($user->canManageAllTenants());
        $this->assertFalse($user->canManageDeploymentRequests());
        $this->assertTrue($user->canAccessCountry('SLE'));
        $this->assertFalse($user->canAccessCountry('LBR'));
    }

    public function test_database_seeder_uses_env_backed_passwords_and_client_admin_role(): void
    {
        config([
            'borderreach.platform_mode' => false,
            'borderreach.tenant_country_code' => 'SLE',
            'borderreach.seed.admin_name' => 'Country Admin',
            'borderreach.seed.admin_email' => 'country-admin@example.test',
            'borderreach.seed.admin_password' => 'CountryPass123!',
        ]);

        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('email', 'country-admin@example.test')->firstOrFail();

        $this->assertSame(User::ROLE_HQ_ADMIN, $admin->role);
        $this->assertSame('SLE', $admin->country_code);
        $this->assertTrue(Hash::check('CountryPass123!', $admin->password));
        $this->assertFalse($admin->canManageDeploymentRequests());
    }
}
