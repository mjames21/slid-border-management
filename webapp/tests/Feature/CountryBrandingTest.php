<?php

namespace Tests\Feature;

use App\Models\BorderPost;
use App\Models\Country;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CountryBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_country_app_branding_and_logo(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);

        $this->actingAs($admin)->post('/admin/countries/LBR', [
            'name' => 'Liberia',
            'immigration_agency' => 'Liberia Immigration Service',
            'app_title' => 'Liberia Immigration Border Management',
            'app_subtitle' => 'Bureau of Immigration and Naturalization',
            'timezone' => 'Africa/Monrovia',
            'is_active' => '1',
            'logo' => UploadedFile::fake()->image('liberia-logo.png', 120, 120),
        ])->assertRedirect(route('admin.countries.index'));

        $country = Country::query()->findOrFail('LBR');

        $this->assertSame('Liberia Immigration Border Management', $country->app_title);
        $this->assertSame('Bureau of Immigration and Naturalization', $country->app_subtitle);
        $this->assertTrue($country->is_active);
        $this->assertSame('image/png', $country->logo_mime_type);
        Storage::disk('public')->assertExists($country->logo_path);
    }

    public function test_mobile_auth_and_config_return_country_branding(): void
    {
        Country::query()->whereKey('LBR')->update([
            'is_active' => true,
            'app_title' => 'Liberia Immigration Border Management',
            'app_subtitle' => 'Liberia Immigration Service',
        ]);

        $post = BorderPost::query()->create([
            'country_code' => 'LBR',
            'code' => 'BOW_LBR_BRANDING',
            'name' => 'Bo Waterside Branding',
            'region' => 'Grand Cape Mount',
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'email' => 'liberia.branding@example.test',
            'password' => Hash::make('Password123!'),
            'country_code' => 'LBR',
            'border_post_id' => $post->id,
            'role' => 'border_officer',
            'is_active' => true,
        ]);

        $this->postJson('/api/mobile/auth/login', [
            'email' => $user->email,
            'password' => 'Password123!',
            'device_name' => 'liberia-branding-device',
        ])->assertOk()
            ->assertJsonPath('branding.countryCode', 'LBR')
            ->assertJsonPath('branding.appTitle', 'Liberia Immigration Border Management')
            ->assertJsonPath('branding.appSubtitle', 'Liberia Immigration Service')
            ->assertJsonPath('branding.logoMimeType', 'image/png')
            ->assertJson(fn ($json) => $json->whereType('branding.logoBase64', 'string')->etc());

        Sanctum::actingAs($user, ['mobile:read']);

        $this->getJson('/api/mobile/config')
            ->assertOk()
            ->assertJsonPath('branding.countryCode', 'LBR')
            ->assertJsonPath('branding.appTitle', 'Liberia Immigration Border Management');
    }

    public function test_public_mobile_branding_endpoint_returns_configured_country_profile(): void
    {
        Country::query()->whereKey('GIN')->update([
            'app_title' => 'Guinea Immigration Border Management',
            'app_subtitle' => 'Guinea Border Services',
        ]);

        $this->getJson('/api/mobile/branding?country=GIN')
            ->assertOk()
            ->assertJsonPath('countryCode', 'GIN')
            ->assertJsonPath('appTitle', 'Guinea Immigration Border Management')
            ->assertJsonPath('appSubtitle', 'Guinea Border Services')
            ->assertJsonPath('logoMimeType', 'image/png')
            ->assertJson(fn ($json) => $json->whereType('logoBase64', 'string')->etc());
    }
}
