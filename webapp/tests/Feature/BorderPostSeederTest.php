<?php

namespace Tests\Feature;

use App\Models\BorderPost;
use App\Models\User;
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

        $this->assertDatabaseCount('border_posts', 13);
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

        $officer->refresh();

        $this->assertSame('BEN-LND', $officer->borderPost?->code);
    }
}
