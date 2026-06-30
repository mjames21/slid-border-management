<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('mobile_submissions', function (Blueprint $table) {
            $table->decimal('device_latitude', 10, 7)->nullable()->after('region');
            $table->decimal('device_longitude', 10, 7)->nullable()->after('device_latitude');
            $table->decimal('device_location_accuracy_meters', 8, 2)->nullable()->after('device_longitude');
            $table->timestamp('device_location_captured_at')->nullable()->after('device_location_accuracy_meters');
        });
    }

    public function down(): void
    {
        Schema::table('mobile_submissions', function (Blueprint $table) {
            $table->dropColumn([
                'device_latitude',
                'device_longitude',
                'device_location_accuracy_meters',
                'device_location_captured_at',
            ]);
        });
    }
};
