<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('frequent_locations', function (Blueprint $table) {
            $table->string('district')->nullable()->after('admin_area')->index();
        });

        DB::table('frequent_locations')
            ->whereNull('district')
            ->orderBy('id')
            ->select(['id', 'admin_area'])
            ->lazyById()
            ->each(function (object $location): void {
                $adminArea = trim((string) $location->admin_area);
                if ($adminArea === '') {
                    return;
                }

                $district = trim(strtok($adminArea, '/') ?: $adminArea);
                if ($district === '') {
                    return;
                }

                DB::table('frequent_locations')
                    ->where('id', $location->id)
                    ->update(['district' => $district]);
            });
    }

    public function down(): void
    {
        Schema::table('frequent_locations', function (Blueprint $table) {
            $table->dropColumn('district');
        });
    }
};
