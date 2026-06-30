<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->string('app_title')->nullable()->after('immigration_agency');
            $table->string('app_subtitle')->nullable()->after('app_title');
            $table->string('logo_path')->nullable()->after('app_subtitle');
            $table->string('logo_mime_type')->nullable()->after('logo_path');
        });

        DB::table('countries')->where('code', 'SLE')->update([
            'app_title' => 'SLID Border Reporting',
            'app_subtitle' => 'Sierra Leone Immigration Service',
            'updated_at' => now(),
        ]);

        DB::table('countries')->where('code', 'LBR')->update([
            'app_title' => 'Liberia Border Reporting',
            'app_subtitle' => 'Liberia Immigration Service',
            'updated_at' => now(),
        ]);

        DB::table('countries')->where('code', 'GIN')->update([
            'app_title' => 'Guinea Border Reporting',
            'app_subtitle' => 'Guinea Border Services',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->dropColumn(['app_title', 'app_subtitle', 'logo_path', 'logo_mime_type']);
        });
    }
};
