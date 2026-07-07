<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('frequent_locations', function (Blueprint $table) {
            $table->string('code', 80)->nullable()->after('id')->unique('frequent_locations_code_unique');
        });

        DB::table('frequent_locations')
            ->orderBy('id')
            ->get(['id', 'country_code', 'name', 'admin_area'])
            ->each(function (object $location): void {
                $base = collect([$location->country_code, $location->name, $location->admin_area])
                    ->filter()
                    ->implode('-');

                DB::table('frequent_locations')
                    ->where('id', $location->id)
                    ->update(['code' => Str::upper(Str::slug($base, '-'))]);
            });
    }

    public function down(): void
    {
        Schema::table('frequent_locations', function (Blueprint $table) {
            $table->dropUnique('frequent_locations_code_unique');
            $table->dropColumn('code');
        });
    }
};
