<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->string('boundary_geojson_path')->nullable()->after('logo_mime_type');
            $table->string('boundary_source_name')->nullable()->after('boundary_geojson_path');
            $table->string('boundary_source_type')->nullable()->after('boundary_source_name');
            $table->timestamp('boundary_imported_at')->nullable()->after('boundary_source_type');
        });
    }

    public function down(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->dropColumn([
                'boundary_geojson_path',
                'boundary_source_name',
                'boundary_source_type',
                'boundary_imported_at',
            ]);
        });
    }
};
