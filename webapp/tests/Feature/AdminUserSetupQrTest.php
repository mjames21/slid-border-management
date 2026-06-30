<?php

namespace Tests\Feature;

use App\Models\BorderPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserSetupQrTest extends TestCase
{
    use RefreshDatabase;

    public function test_setup_qr_prefills_configured_mobile_setup_host_when_browser_uses_localhost(): void
    {
        config(['app.mobile_setup_host' => '192.168.1.242']);

        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $user = User::factory()->create([
            'is_admin' => false,
            'is_active' => true,
            'country_code' => 'SLE',
            'role' => 'border_officer',
        ]);

        $this->actingAs($admin)
            ->withServerVariables([
                'HTTP_HOST' => '127.0.0.1:8010',
                'SERVER_PORT' => 8010,
            ])
            ->get(route('admin.users.setup-qr', $user))
            ->assertOk()
            ->assertSee('http://192.168.1.242:8010/');
    }

    public function test_admin_can_generate_mobile_setup_qr_with_temporary_password(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $borderPost = BorderPost::query()->create([
            'country_code' => 'SLE',
            'code' => 'QR_TEST',
            'name' => 'QR Test Border Post',
            'region' => 'Kambia',
            'is_active' => true,
        ]);
        $user = User::factory()->create([
            'is_admin' => false,
            'is_active' => false,
            'country_code' => 'SLE',
            'border_post_id' => $borderPost->id,
            'role' => 'border_officer',
            'email' => 'qr.officer@slid.local',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.users.setup-qr.generate', $user), [
            'server_url' => 'http://192.168.1.242:8010',
            'device_name' => 'tablet-01',
        ]);

        $response
            ->assertOk()
            ->assertSee('slid_mobile_setup')
            ->assertSee('http://192.168.1.242:8010/')
            ->assertSee('qr.officer@slid.local')
            ->assertSee('<svg', false);

        preg_match('/SLID-[A-Z0-9]{4}-[0-9]{4}/', $response->getContent(), $matches);
        $this->assertNotEmpty($matches);

        $user->refresh();

        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check($matches[0], $user->password));
    }

    public function test_setup_qr_rejects_loopback_server_urls_that_a_phone_cannot_reach(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $user = User::factory()->create([
            'is_admin' => false,
            'is_active' => true,
            'country_code' => 'SLE',
            'role' => 'border_officer',
        ]);

        $this->actingAs($admin)->post(route('admin.users.setup-qr.generate', $user), [
            'server_url' => 'http://127.0.0.1:8010',
            'device_name' => 'tablet-01',
        ])->assertSessionHasErrors('server_url');
    }
}
