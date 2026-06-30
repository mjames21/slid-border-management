<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->string('code', 3)->primary();
            $table->string('name');
            $table->string('immigration_agency')->nullable();
            $table->string('timezone')->default('Africa/Freetown');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        DB::table('countries')->insert([
            [
                'code' => 'SLE',
                'name' => 'Sierra Leone',
                'immigration_agency' => 'Sierra Leone Immigration Service',
                'timezone' => 'Africa/Freetown',
                'is_active' => true,
                'sort_order' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'LBR',
                'name' => 'Liberia',
                'immigration_agency' => 'Liberia Immigration Service',
                'timezone' => 'Africa/Monrovia',
                'is_active' => false,
                'sort_order' => 20,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'GIN',
                'name' => 'Guinea',
                'immigration_agency' => 'Guinea Border Services',
                'timezone' => 'Africa/Conakry',
                'is_active' => false,
                'sort_order' => 30,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Schema::table('border_posts', function (Blueprint $table) {
            $table->string('country_code', 3)->default('SLE')->after('id')->index();
        });

        Schema::table('dynamic_forms', function (Blueprint $table) {
            $table->string('country_code', 3)->default('SLE')->after('id')->index();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('country_code', 3)->default('SLE')->after('is_admin')->index();
        });

        Schema::table('mobile_devices', function (Blueprint $table) {
            $table->string('country_code', 3)->default('SLE')->after('border_post_id')->index();
        });

        Schema::table('mobile_submissions', function (Blueprint $table) {
            $table->string('country_code', 3)->default('SLE')->after('border_post_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('mobile_submissions', function (Blueprint $table) {
            $table->dropColumn('country_code');
        });

        Schema::table('mobile_devices', function (Blueprint $table) {
            $table->dropColumn('country_code');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('country_code');
        });

        Schema::table('dynamic_forms', function (Blueprint $table) {
            $table->dropColumn('country_code');
        });

        Schema::table('border_posts', function (Blueprint $table) {
            $table->dropColumn('country_code');
        });

        Schema::dropIfExists('countries');
    }
};
