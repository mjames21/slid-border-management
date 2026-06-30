<?php

namespace Tests\Feature;

use App\Models\BorderPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BorderPostManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_border_post_with_lonlat(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);

        $this->actingAs($admin)->post('/admin/border-posts', [
            'country_code' => 'SLE',
            'code' => 'KAM_GBALAMUYA',
            'name' => 'Gbalamuya Border Post',
            'region' => 'Kambia',
            'longitude' => '-12.8745000',
            'latitude' => '9.8356000',
            'allowed_radius_meters' => '300',
            'is_active' => '1',
        ])->assertRedirect(route('admin.border-posts.index'));

        $this->assertDatabaseHas('border_posts', [
            'code' => 'KAM_GBALAMUYA',
            'country_code' => 'SLE',
            'name' => 'Gbalamuya Border Post',
            'digital_address' => 'SLE-BP-KAM-GBALAMUYA',
            'region' => 'Kambia',
            'latitude' => '9.8356000',
            'longitude' => '-12.8745000',
            'allowed_radius_meters' => 300,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_update_border_post_lonlat(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $borderPost = BorderPost::query()->create([
            'country_code' => 'LBR',
            'code' => 'BOW_LBR_TEST',
            'name' => 'Bo Waterside Test',
            'region' => 'Grand Cape Mount',
            'is_active' => true,
        ]);

        $this->actingAs($admin)->post("/admin/border-posts/{$borderPost->id}", [
            'country_code' => 'LBR',
            'code' => 'BOW_LBR_TEST',
            'name' => 'Bo Waterside Test',
            'digital_address' => 'lbr bp custom bow',
            'region' => 'Grand Cape Mount',
            'longitude' => '-11.3751000',
            'latitude' => '6.7523000',
            'allowed_radius_meters' => '500',
            'is_active' => '1',
        ])->assertRedirect(route('admin.border-posts.index'));

        $borderPost->refresh();

        $this->assertSame('6.7523000', $borderPost->latitude);
        $this->assertSame('-11.3751000', $borderPost->longitude);
        $this->assertSame(500, $borderPost->allowed_radius_meters);
        $this->assertSame('LBR-BP-CUSTOM-BOW', $borderPost->digital_address);
    }
}
