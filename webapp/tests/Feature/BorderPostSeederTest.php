<?php

namespace Tests\Feature;

use App\Models\BorderPost;
use App\Models\User;
use Database\Seeders\BorderOfficerSeeder;
use Database\Seeders\BorderPostSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BorderPostSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_border_post_seeder_loads_operational_sierra_leone_posts_once(): void
    {
        $legacyPost = BorderPost::query()->where('code', 'FAL_FALABA')->firstOrFail();
        $officer = User::factory()->create([
            'country_code' => 'SLE',
            'border_post_id' => $legacyPost->id,
            'role' => 'border_officer',
            'is_active' => true,
        ]);

        $this->seed(BorderPostSeeder::class);
        $this->seed(BorderPostSeeder::class);

        $this->assertDatabaseCount('border_posts', 16);
        $this->assertDatabaseMissing('border_posts', ['code' => 'FAL_FALABA']);
        $this->assertDatabaseHas('border_posts', [
            'code' => 'SBY-SEA',
            'name' => "Susan's Bay",
            'region' => 'Western Area Urban / WEST - Western Area',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('border_posts', [
            'code' => 'GBM-LND',
            'name' => 'Gbalamuya',
            'region' => 'Kambia / NORTH_WEST - North West Province',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('border_posts', [
            'code' => 'FAL-LND',
            'name' => 'Falaba',
            'region' => 'Falaba / NORTH - Northern Province',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('border_posts', [
            'code' => 'KNU-LND',
            'name' => 'Koinukura',
            'region' => 'Falaba / NORTH - Northern Province',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('border_posts', [
            'code' => 'YEN-LND',
            'name' => 'Yenga',
            'region' => 'Kailahun / EAST - Eastern Province',
            'is_active' => true,
        ]);

        $officer->refresh();

        $this->assertSame('BEN-LND', $officer->borderPost?->code);
    }

    public function test_border_officer_seeder_creates_one_login_for_each_active_border_post(): void
    {
        $this->seed(BorderPostSeeder::class);
        $this->seed(BorderOfficerSeeder::class);
        $this->seed(BorderOfficerSeeder::class);

        $activePostIds = BorderPost::query()->where('is_active', true)->pluck('id');

        $this->assertCount(16, $activePostIds);
        $this->assertSame(
            16,
            User::query()
                ->where('role', 'border_officer')
                ->whereIn('border_post_id', $activePostIds)
                ->count()
        );

        $this->assertDatabaseHas('users', [
            'email' => 'ben-lnd.officer@slid.local',
            'name' => 'Bendu Border Officer',
            'country_code' => 'SLE',
            'border_post_id' => BorderPost::query()->where('code', 'BEN-LND')->value('id'),
            'role' => 'border_officer',
            'is_admin' => false,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'gbm-lnd.officer@slid.local',
            'name' => 'Gbalamuya Border Officer',
            'country_code' => 'SLE',
            'border_post_id' => BorderPost::query()->where('code', 'GBM-LND')->value('id'),
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'fal-lnd.officer@slid.local',
            'name' => 'Falaba Border Officer',
            'country_code' => 'SLE',
            'border_post_id' => BorderPost::query()->where('code', 'FAL-LND')->value('id'),
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'knu-lnd.officer@slid.local',
            'name' => 'Koinukura Border Officer',
            'country_code' => 'SLE',
            'border_post_id' => BorderPost::query()->where('code', 'KNU-LND')->value('id'),
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'yen-lnd.officer@slid.local',
            'name' => 'Yenga Border Officer',
            'country_code' => 'SLE',
            'border_post_id' => BorderPost::query()->where('code', 'YEN-LND')->value('id'),
        ]);
    }
}
