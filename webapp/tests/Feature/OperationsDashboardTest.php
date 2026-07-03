<?php

namespace Tests\Feature;

use App\Models\BorderPost;
use App\Models\Country;
use App\Models\DashboardView;
use App\Models\DynamicForm;
use App\Models\MobileSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class OperationsDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_upload_country_boundary_geojson(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $boundary = UploadedFile::fake()->createWithContent('sierra-leone.geojson', json_encode($this->sampleBoundary()));

        $this->actingAs($admin)->post('/admin/countries/SLE', [
            'name' => 'Sierra Leone',
            'immigration_agency' => 'Sierra Leone Immigration Service',
            'app_title' => 'SLID Border Reporting',
            'app_subtitle' => 'Sierra Leone Immigration Service',
            'timezone' => 'Africa/Freetown',
            'is_active' => '1',
            'boundary_file' => $boundary,
        ])->assertRedirect(route('admin.countries.index'));

        $country = Country::query()->findOrFail('SLE');

        $this->assertSame('sierra-leone.geojson', $country->boundary_source_name);
        Storage::disk('public')->assertExists($country->boundary_geojson_path);
    }

    public function test_admin_can_open_dedicated_report_map_page(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.map.index'))
            ->assertOk()
            ->assertSee('View Reports on Map')
            ->assertSee('Upload Boundary')
            ->assertSee('Country boundary with GPS report points');
    }

    public function test_country_boundary_upload_rejects_unapproved_extensions(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $boundary = UploadedFile::fake()->createWithContent('boundary.php', json_encode($this->sampleBoundary()));

        $this->actingAs($admin)->post('/admin/countries/SLE', [
            'name' => 'Sierra Leone',
            'immigration_agency' => 'Sierra Leone Immigration Service',
            'app_title' => 'SLID Border Reporting',
            'app_subtitle' => 'Sierra Leone Immigration Service',
            'timezone' => 'Africa/Freetown',
            'is_active' => '1',
            'boundary_file' => $boundary,
        ])->assertSessionHasErrors('boundary_file');
    }

    public function test_country_boundary_upload_accepts_gis_zip_with_metadata_files(): void
    {
        if (!class_exists(ZipArchive::class)) {
            $this->markTestSkipped('PHP zip extension is not available.');
        }

        Storage::fake('public');

        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $boundary = $this->zipUpload([
            'GIS-ShapeFiles/.DS_Store' => 'metadata',
            '__MACOSX/GIS-ShapeFiles/._regions' => 'metadata',
            'GIS-ShapeFiles/readme.txt' => 'not boundary data',
            'GIS-ShapeFiles/regions/regions.geojson' => json_encode($this->sampleBoundary()),
        ]);

        $this->actingAs($admin)->post('/admin/countries/SLE', [
            'name' => 'Sierra Leone',
            'immigration_agency' => 'Sierra Leone Immigration Service',
            'app_title' => 'SLID Border Reporting',
            'app_subtitle' => 'Sierra Leone Immigration Service',
            'timezone' => 'Africa/Freetown',
            'is_active' => '1',
            'boundary_file' => $boundary,
        ])->assertRedirect(route('admin.countries.index'));

        $country = Country::query()->findOrFail('SLE');

        Storage::disk('public')->assertExists($country->boundary_geojson_path);
    }

    public function test_country_boundary_upload_rejects_zip_files_with_too_many_members(): void
    {
        if (!class_exists(ZipArchive::class)) {
            $this->markTestSkipped('PHP zip extension is not available.');
        }

        Storage::fake('public');

        $entries = [];
        for ($index = 0; $index < 251; $index++) {
            $entries["notes-{$index}.txt"] = 'not boundary data';
        }
        $entries['boundary.geojson'] = json_encode($this->sampleBoundary());

        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $boundary = $this->zipUpload($entries);

        $this->actingAs($admin)->post('/admin/countries/SLE', [
            'name' => 'Sierra Leone',
            'immigration_agency' => 'Sierra Leone Immigration Service',
            'app_title' => 'SLID Border Reporting',
            'app_subtitle' => 'Sierra Leone Immigration Service',
            'timezone' => 'Africa/Freetown',
            'is_active' => '1',
            'boundary_file' => $boundary,
        ])->assertSessionHasErrors('boundary_file');
    }

    public function test_dashboard_data_returns_boundary_points_and_aggregates(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $country = Country::query()->findOrFail('SLE');
        $country->forceFill([
            'boundary_geojson_path' => 'country-boundaries/SLE.geojson',
            'boundary_source_name' => 'sierra-leone.geojson',
            'boundary_source_type' => 'geojson',
            'boundary_imported_at' => now(),
        ])->save();
        Storage::disk('public')->put('country-boundaries/SLE.geojson', json_encode($this->sampleBoundary()));

        $post = BorderPost::query()->create([
            'country_code' => 'SLE',
            'code' => 'FAL_DASH_TEST',
            'digital_address' => 'SLE-BP-FAL-DASH-TEST',
            'name' => 'Falaba Dashboard Test',
            'region' => 'Falaba',
            'is_active' => true,
        ]);
        $form = DynamicForm::query()->create([
            'country_code' => 'SLE',
            'reporting_module' => DynamicForm::MODULE_HEALTH,
            'form_id' => 'dashboard_form',
            'title' => 'Dashboard Form',
        ]);

        MobileSubmission::query()->create([
            'server_uid' => '00000000-0000-4000-8000-000000000001',
            'user_id' => $admin->id,
            'border_post_id' => $post->id,
            'country_code' => 'SLE',
            'border_post_code' => 'FAL_DASH_TEST',
            'border_post_digital_address' => 'SLE-BP-FAL-DASH-TEST',
            'region' => 'Falaba',
            'reporting_module' => DynamicForm::MODULE_HEALTH,
            'device_latitude' => '9.7401000',
            'device_longitude' => '-11.6502000',
            'device_location_accuracy_meters' => '14.25',
            'device_location_captured_at' => now(),
            'device_id' => 'dashboard-device',
            'local_id' => 'dash-local-1',
            'form_id' => $form->form_id,
            'form_version' => 1,
            'answers' => [
                'movement_type' => ['entry'],
                'officer_decision' => ['cleared'],
                'document_type' => ['passport'],
                'full_name' => ['Aminata Conteh'],
                'id_number' => ['P1234567'],
            ],
            'client_created_at' => now()->subMinutes(10),
            'client_updated_at' => now()->subMinutes(10),
            'received_at' => now()->subMinutes(10),
            'status' => 'accepted',
        ]);

        MobileSubmission::query()->create([
            'server_uid' => '00000000-0000-4000-8000-000000000002',
            'user_id' => $admin->id,
            'border_post_id' => $post->id,
            'country_code' => 'SLE',
            'border_post_code' => 'FAL_DASH_TEST',
            'border_post_digital_address' => 'SLE-BP-FAL-DASH-TEST',
            'region' => 'Falaba',
            'reporting_module' => DynamicForm::MODULE_HEALTH,
            'device_id' => 'dashboard-device-no-gps',
            'local_id' => 'dash-local-2',
            'form_id' => $form->form_id,
            'form_version' => 1,
            'answers' => [
                'movement_type' => ['exit'],
                'inspection_decision' => ['review'],
                'travel_document_type' => ['emergency travel certificate'],
                'full_name' => ['Mohamed Kamara'],
                'id_number' => ['ETD7654321'],
            ],
            'client_created_at' => now(),
            'client_updated_at' => now(),
            'received_at' => now(),
            'status' => 'rejected',
        ]);

        $this->actingAs($admin)->getJson('/admin/dashboard/data?country_code=SLE&hours=24')
            ->assertOk()
            ->assertJsonPath('country.code', 'SLE')
            ->assertJsonPath('metrics.total', 2)
            ->assertJsonPath('metrics.withLocation', 1)
            ->assertJsonPath('metrics.withoutLocation', 1)
            ->assertJsonPath('metrics.gpsCoveragePercent', 50)
            ->assertJsonPath('metrics.uniqueDevices', 2)
            ->assertJsonPath('metrics.rejected', 1)
            ->assertJsonPath('boundary.type', 'FeatureCollection')
            ->assertJsonPath('points.0.borderPostCode', 'FAL_DASH_TEST')
            ->assertJsonPath('points.0.borderPostDigitalAddress', 'SLE-BP-FAL-DASH-TEST')
            ->assertJsonPath('points.0.longitude', -11.6502)
            ->assertJsonPath('points.0.latitude', 9.7401)
            ->assertJsonPath('latestReports.0.localId', 'dash-local-2')
            ->assertJsonPath('latestReports.0.serverId', '00000000-0000-4000-8000-000000000002')
            ->assertJsonPath('latestReports.0.reportingModule', DynamicForm::MODULE_HEALTH)
            ->assertJsonPath('latestReports.0.reportingModuleLabel', 'Health / Quarantine')
            ->assertJsonPath('latestReports.0.latitude', null)
            ->assertJsonPath('aggregates.byModule.0.label', 'Health / Quarantine')
            ->assertJsonPath('aggregates.byModule.0.value', DynamicForm::MODULE_HEALTH)
            ->assertJsonPath('aggregates.byModule.0.total', 2)
            ->assertJsonPath('aggregates.byBorderPost.0.label', 'FAL_DASH_TEST')
            ->assertJsonPath('aggregates.byBorderPost.0.total', 2)
            ->assertJsonPath('analysis.statusBreakdown.0.label', 'rejected')
            ->assertJsonPath('analysis.modules.0.label', 'Health / Quarantine')
            ->assertJsonPath('analysis.movementTypes.0.label', 'exit')
            ->assertJsonPath('analysis.decisions.0.label', 'review')
            ->assertJsonPath('analysis.documentTypes.0.label', 'emergency travel certificate')
            ->assertJsonPath('analysis.syncLatency.0.label', 'Under 5 min')
            ->assertJsonPath('analysis.dataQuality.0.total', 1)
            ->assertJsonPath('analysis.formVersions.0.label', 'dashboard_form v1')
            ->assertJsonPath('analysis.gpsQuality.0.total', 1)
            ->assertJsonPath('analysis.gpsQuality.3.total', 1);
    }

    public function test_dashboard_data_applies_visual_filters(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);

        MobileSubmission::query()->create([
            'server_uid' => '00000000-0000-4000-8000-000000000101',
            'user_id' => $admin->id,
            'country_code' => 'SLE',
            'border_post_code' => 'KAM_TEST',
            'region' => 'Kambia',
            'device_latitude' => '9.8356000',
            'device_longitude' => '-12.8745000',
            'device_id' => 'filter-device-1',
            'local_id' => 'filter-local-1',
            'form_id' => 'movement_form',
            'form_version' => 1,
            'answers' => [
                'movement_type' => ['entry'],
                'full_name' => ['Aminata Conteh'],
                'id_number' => ['P1234567'],
            ],
            'client_created_at' => now(),
            'client_updated_at' => now(),
            'received_at' => now(),
            'status' => 'accepted',
        ]);

        MobileSubmission::query()->create([
            'server_uid' => '00000000-0000-4000-8000-000000000102',
            'user_id' => $admin->id,
            'country_code' => 'SLE',
            'border_post_code' => 'BO_TEST',
            'region' => 'Bo',
            'device_id' => 'filter-device-2',
            'local_id' => 'filter-local-2',
            'form_id' => 'movement_form',
            'form_version' => 1,
            'answers' => [
                'movement_type' => ['exit'],
                'full_name' => ['John Sheriff'],
                'id_number' => ['X7654321'],
            ],
            'client_created_at' => now(),
            'client_updated_at' => now(),
            'received_at' => now(),
            'status' => 'rejected',
        ]);

        $query = http_build_query([
            'country_code' => 'SLE',
            'hours' => 24,
            'filters' => json_encode([
                ['field' => 'status', 'operator' => 'equals', 'value' => 'accepted'],
                ['field' => 'movement_type', 'operator' => 'contains', 'value' => 'entry'],
            ]),
        ]);

        $this->actingAs($admin)->getJson('/admin/dashboard/data?'.$query)
            ->assertOk()
            ->assertJsonPath('metrics.total', 1)
            ->assertJsonPath('metrics.withLocation', 1)
            ->assertJsonPath('points.0.borderPostCode', 'KAM_TEST')
            ->assertJsonPath('aggregates.byRegion.0.label', 'Kambia');
    }

    public function test_dashboard_data_applies_discover_search(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);

        MobileSubmission::query()->create([
            'server_uid' => '00000000-0000-4000-8000-000000000201',
            'user_id' => $admin->id,
            'country_code' => 'SLE',
            'border_post_code' => 'FAL_SEARCH',
            'border_post_digital_address' => 'SLE-BP-FAL-SEARCH',
            'region' => 'Falaba',
            'device_id' => 'discover-device-1',
            'local_id' => 'discover-local-1',
            'form_id' => 'movement_form',
            'form_version' => 1,
            'answers' => [
                'full_name' => ['Aminata Conteh'],
                'id_number' => ['P1234567'],
            ],
            'client_created_at' => now()->subMinutes(20),
            'client_updated_at' => now()->subMinutes(20),
            'received_at' => now(),
            'status' => 'accepted',
        ]);

        MobileSubmission::query()->create([
            'server_uid' => '00000000-0000-4000-8000-000000000202',
            'user_id' => $admin->id,
            'country_code' => 'SLE',
            'border_post_code' => 'KAM_SEARCH',
            'border_post_digital_address' => 'SLE-BP-KAM-SEARCH',
            'region' => 'Kambia',
            'device_id' => 'discover-device-2',
            'local_id' => 'discover-local-2',
            'form_id' => 'movement_form',
            'form_version' => 1,
            'answers' => [
                'full_name' => ['John Sheriff'],
                'id_number' => ['X7654321'],
            ],
            'client_created_at' => now()->subMinutes(20),
            'client_updated_at' => now()->subMinutes(20),
            'received_at' => now(),
            'status' => 'accepted',
        ]);

        $query = http_build_query([
            'country_code' => 'SLE',
            'hours' => 24,
            'q' => 'SLE-BP-FAL-SEARCH',
        ]);

        $this->actingAs($admin)->getJson('/admin/dashboard/data?'.$query)
            ->assertOk()
            ->assertJsonPath('metrics.total', 1)
            ->assertJsonPath('latestReports.0.borderPostCode', 'FAL_SEARCH')
            ->assertJsonPath('latestReports.0.borderPostDigitalAddress', 'SLE-BP-FAL-SEARCH')
            ->assertJsonPath('latestReports.0.documentNumber', 'P1234567');
    }

    public function test_admin_can_save_dashboard_view(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);

        $this->actingAs($admin)->postJson('/admin/dashboard/views', [
            'name' => 'Falaba Entry Watch',
            'country_code' => 'SLE',
            'time_window_hours' => 72,
            'filters' => [
                ['field' => 'status', 'operator' => 'equals', 'value' => 'accepted'],
                ['field' => 'border_post_code', 'operator' => 'contains', 'value' => 'FAL'],
            ],
            'layout' => ['map', 'reports', 'detail', 'aggregates'],
            'is_default' => true,
        ])
            ->assertOk()
            ->assertJsonPath('view.name', 'Falaba Entry Watch')
            ->assertJsonPath('view.timeWindowHours', 72)
            ->assertJsonPath('view.layout.1', 'reports')
            ->assertJsonPath('view.isDefault', true);

        $view = DashboardView::query()->firstOrFail();

        $this->assertSame($admin->id, $view->user_id);
        $this->assertSame('SLE', $view->country_code);
        $this->assertTrue($view->is_default);
        $this->assertSame('accepted', $view->filters[0]['value']);
    }

    private function sampleBoundary(): array
    {
        return [
            'type' => 'FeatureCollection',
            'features' => [[
                'type' => 'Feature',
                'properties' => ['name' => 'Sierra Leone'],
                'geometry' => [
                    'type' => 'Polygon',
                    'coordinates' => [[
                        [-13.5, 6.8],
                        [-10.2, 6.8],
                        [-10.2, 10.1],
                        [-13.5, 10.1],
                        [-13.5, 6.8],
                    ]],
                ],
            ]],
        ];
    }

    /**
     * @param  array<string, string>  $entries
     */
    private function zipUpload(array $entries): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'boundary-');
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::OVERWRITE);

        foreach ($entries as $name => $contents) {
            $zip->addFromString($name, $contents);
        }

        $zip->close();

        return new UploadedFile($path, 'boundary.zip', 'application/zip', null, true);
    }
}
