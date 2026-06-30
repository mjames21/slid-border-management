<?php

namespace Tests\Feature;

use App\Models\FrequentLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FrequentLocationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_upload_frequent_locations_csv(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $path = tempnam(sys_get_temp_dir(), 'locations_');
        file_put_contents($path, "country,name,admin_area,category,sort_order\nSLE,Kambia,Kambia,town,10\nGuinea Conakry,Pamelap,Forecariah,border town,20\nLiberia,Bo Waterside,Grand Cape Mount,border town,30\n");

        $file = new UploadedFile($path, 'locations.csv', 'text/csv', null, true);

        $this->actingAs($admin)
            ->post('/admin/locations', ['file' => $file])
            ->assertRedirect(route('admin.locations.index'));

        $this->assertDatabaseHas('frequent_locations', [
            'country_code' => 'SLE',
            'name' => 'Kambia',
            'admin_area' => 'Kambia',
        ]);
        $this->assertDatabaseHas('frequent_locations', [
            'country_code' => 'GIN',
            'name' => 'Pamelap',
            'admin_area' => 'Forecariah',
        ]);
        $this->assertDatabaseHas('frequent_locations', [
            'country_code' => 'LBR',
            'name' => 'Bo Waterside',
            'admin_area' => 'Grand Cape Mount',
        ]);
    }

    public function test_location_option_source_is_hydrated_for_mobile_sync(): void
    {
        FrequentLocation::query()->create([
            'country_code' => 'SLE',
            'country_name' => 'Sierra Leone',
            'name' => 'Kambia',
            'admin_area' => 'Kambia',
            'is_active' => true,
        ]);
        FrequentLocation::query()->create([
            'country_code' => 'GIN',
            'country_name' => 'Guinea',
            'name' => 'Pamelap',
            'admin_area' => 'Forecariah',
            'is_active' => true,
        ]);

        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);

        $this->actingAs($admin)->post('/admin/forms/builder', [
            'form_id' => 'gin_location_sync',
            'title' => 'Guinea Location Sync',
            'publish' => '1',
            'fields' => [[
                'include' => '1',
                'id' => 'origin_location',
                'type' => 'select_one',
                'label' => 'From Location',
                'required' => '1',
                'option_source' => 'locations:GIN',
            ]],
        ])->assertRedirect();

        Sanctum::actingAs(User::factory()->create(), ['mobile:read']);

        $response = $this->getJson('/api/mobile/config')->assertOk();
        $options = collect($response->json('activeForms.0.fields.0.options'));

        $this->assertTrue($options->contains(fn (array $option): bool => $option['label'] === 'Pamelap, Forecariah (GIN)'));
        $this->assertFalse($options->contains(fn (array $option): bool => str_contains($option['label'], 'Kambia')));
    }
}
