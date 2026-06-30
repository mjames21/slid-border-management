<?php

namespace Tests\Feature;

use App\Models\BorderPost;
use App\Models\MobileDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MobileEnterpriseAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_unassigned_user_cannot_login_to_mobile_api(): void
    {
        $user = User::factory()->create([
            'email' => 'unassigned.mobile@gmail.com',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
            'border_post_id' => null,
        ]);

        $response = $this->postJson('/api/mobile/auth/login', [
            'email' => $user->email,
            'password' => 'Password123!',
            'device_name' => 'android-test-device',
        ]);

        $response->assertForbidden();
    }

    public function test_assigned_user_receives_assignment_and_registers_device(): void
    {
        $post = BorderPost::query()->create([
            'country_code' => 'SLE',
            'code' => 'FAL_FALABA_TEST',
            'digital_address' => 'SLE-BP-FAL-FALABA-TEST',
            'name' => 'Falaba Test Post',
            'region' => 'Falaba',
            'latitude' => 9.7401000,
            'longitude' => -11.6502000,
            'allowed_radius_meters' => 250,
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'email' => 'assigned.mobile@gmail.com',
            'password' => Hash::make('Password123!'),
            'country_code' => 'SLE',
            'is_active' => true,
            'border_post_id' => $post->id,
            'role' => 'border_officer',
        ]);

        $response = $this->postJson('/api/mobile/auth/login', [
            'email' => $user->email,
            'password' => 'Password123!',
            'device_name' => 'android-test-device',
        ]);

        $response->assertOk()
            ->assertJsonPath('assignment.role', 'border_officer')
            ->assertJsonPath('assignment.borderPost.countryCode', 'SLE')
            ->assertJsonPath('assignment.borderPost.code', 'FAL_FALABA_TEST')
            ->assertJsonPath('assignment.borderPost.digitalAddress', 'SLE-BP-FAL-FALABA-TEST')
            ->assertJsonPath('assignment.borderPost.latitude', 9.7401)
            ->assertJsonPath('assignment.borderPost.longitude', -11.6502)
            ->assertJsonPath('assignment.borderPost.allowedRadiusMeters', 250);

        $this->assertDatabaseHas('mobile_devices', [
            'device_id' => 'android-test-device',
            'user_id' => $user->id,
            'border_post_id' => $post->id,
            'country_code' => 'SLE',
        ]);
    }

    public function test_mobile_login_replaces_previous_token_for_same_device(): void
    {
        $post = BorderPost::query()->create([
            'country_code' => 'SLE',
            'code' => 'TOKEN_ROTATE_TEST',
            'name' => 'Token Rotate Test Post',
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'email' => 'token.rotate.mobile@gmail.com',
            'password' => Hash::make('Password123!'),
            'country_code' => 'SLE',
            'is_active' => true,
            'border_post_id' => $post->id,
            'role' => 'border_officer',
        ]);

        $payload = [
            'email' => $user->email,
            'password' => 'Password123!',
            'device_name' => 'same-android-device',
        ];

        $firstToken = $this->postJson('/api/mobile/auth/login', $payload)
            ->assertOk()
            ->json('token');

        $secondToken = $this->postJson('/api/mobile/auth/login', $payload)
            ->assertOk()
            ->json('token');

        $this->assertNotSame($firstToken, $secondToken);
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'same-android-device',
        ]);
    }

    public function test_device_registered_to_another_user_cannot_be_reused(): void
    {
        $post = BorderPost::query()->create([
            'country_code' => 'SLE',
            'code' => 'FAL_REUSE_TEST',
            'name' => 'Falaba Reuse Test',
            'is_active' => true,
        ]);

        $owner = User::factory()->create(['country_code' => 'SLE', 'border_post_id' => $post->id, 'is_active' => true]);
        $other = User::factory()->create([
            'email' => 'other.mobile@gmail.com',
            'password' => Hash::make('Password123!'),
            'country_code' => 'SLE',
            'border_post_id' => $post->id,
            'is_active' => true,
        ]);

        MobileDevice::query()->create([
            'user_id' => $owner->id,
            'border_post_id' => $post->id,
            'country_code' => 'SLE',
            'device_id' => 'android-shared-device',
            'name' => 'android-shared-device',
        ]);

        $response = $this->postJson('/api/mobile/auth/login', [
            'email' => $other->email,
            'password' => 'Password123!',
            'device_name' => 'android-shared-device',
        ]);

        $response->assertForbidden();
    }
}
