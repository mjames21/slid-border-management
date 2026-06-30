<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\FormTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_project_workspace_renders_user_area(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);

        $this->seed(FormTemplateSeeder::class);

        $this->actingAs($admin)
            ->get(route('admin.projects.index'))
            ->assertOk()
            ->assertSee('Projects')
            ->assertSee('Manage standardized border reporting projects')
            ->assertSee('ICAO Doc 9303 Full Inspection')
            ->assertSee('Form builder')
            ->assertSee('Data')
            ->assertSee('Analysis')
            ->assertSee('Team');
    }

    public function test_admin_dashboard_entry_redirects_to_project_workspace(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertRedirect(route('admin.projects.index'));
    }
}
