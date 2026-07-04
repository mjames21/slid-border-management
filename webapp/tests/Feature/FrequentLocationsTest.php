<?php

namespace Tests\Feature;

use App\Models\BorderPost;
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
        file_put_contents($path, "country,name,district,admin_area,category,sort_order\nSLE,Kambia,Kambia,Kambia / Guinea corridor,town,10\nGuinea Conakry,Pamelap,Kambia,Kambia / Guinea corridor,border town,20\nLiberia,Bo Waterside,Pujehun,Pujehun / Liberia corridor,border town,30\n");

        $file = new UploadedFile($path, 'locations.csv', 'text/csv', null, true);

        $this->actingAs($admin)
            ->post('/admin/locations', ['file' => $file])
            ->assertRedirect(route('admin.locations.index'));

        $this->assertDatabaseHas('frequent_locations', [
            'country_code' => 'SLE',
            'name' => 'Kambia',
            'district' => 'Kambia',
            'admin_area' => 'Kambia / Guinea corridor',
        ]);
        $this->assertDatabaseHas('frequent_locations', [
            'country_code' => 'GIN',
            'name' => 'Pamelap',
            'district' => 'Kambia',
            'admin_area' => 'Kambia / Guinea corridor',
        ]);
        $this->assertDatabaseHas('frequent_locations', [
            'country_code' => 'LBR',
            'name' => 'Bo Waterside',
            'district' => 'Pujehun',
            'admin_area' => 'Pujehun / Liberia corridor',
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

    public function test_mobile_location_options_are_scoped_to_assigned_border_post_district(): void
    {
        FrequentLocation::query()->create([
            'country_code' => 'SLE',
            'country_name' => 'Sierra Leone',
            'name' => 'Gbalamuya',
            'district' => 'Kambia',
            'admin_area' => 'Kambia / Guinea corridor',
            'is_active' => true,
        ]);
        FrequentLocation::query()->create([
            'country_code' => 'GIN',
            'country_name' => 'Guinea',
            'name' => 'Pamelap',
            'district' => 'Kambia',
            'admin_area' => 'Kambia / Guinea corridor',
            'is_active' => true,
        ]);
        FrequentLocation::query()->create([
            'country_code' => 'SLE',
            'country_name' => 'Sierra Leone',
            'name' => 'Buedu',
            'district' => 'Kailahun',
            'admin_area' => 'Kailahun / Liberia corridor',
            'is_active' => true,
        ]);
        FrequentLocation::query()->create([
            'country_code' => 'LBR',
            'country_name' => 'Liberia',
            'name' => 'Foya',
            'district' => 'Kailahun',
            'admin_area' => 'Kailahun / Liberia corridor',
            'is_active' => true,
        ]);

        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);

        $this->actingAs($admin)->post('/admin/forms/builder', [
            'form_id' => 'kailahun_location_scope',
            'title' => 'Kailahun Location Scope',
            'publish' => '1',
            'fields' => [[
                'include' => '1',
                'id' => 'origin_location',
                'type' => 'select_one',
                'label' => 'From Location',
                'required' => '1',
                'option_source' => 'locations:all',
            ]],
        ])->assertRedirect();

        $bendu = BorderPost::query()->create([
            'code' => 'BEN-LND',
            'digital_address' => 'SLE-BP-BEN-LND',
            'country_code' => 'SLE',
            'name' => 'Bendu',
            'region' => 'Kailahun / EAST - Eastern Province',
            'is_active' => true,
        ]);

        $officer = User::factory()->create([
            'is_active' => true,
            'country_code' => 'SLE',
            'border_post_id' => $bendu->id,
            'role' => 'border_officer',
        ]);

        Sanctum::actingAs($officer, ['mobile:read']);

        $response = $this->getJson('/api/mobile/config')->assertOk();
        $options = collect($response->json('activeForms.0.fields.0.options'));

        $this->assertTrue($options->contains(fn (array $option): bool => str_contains($option['label'], 'Buedu')));
        $this->assertTrue($options->contains(fn (array $option): bool => str_contains($option['label'], 'Foya')));
        $this->assertFalse($options->contains(fn (array $option): bool => str_contains($option['label'], 'Gbalamuya')));
        $this->assertFalse($options->contains(fn (array $option): bool => str_contains($option['label'], 'Pamelap')));
    }
}
