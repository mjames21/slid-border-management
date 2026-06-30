<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('dynamic_forms', function (Blueprint $table) {
            $table->string('reporting_module', 32)->default('immigration')->after('country_code')->index();
        });

        Schema::table('mobile_submissions', function (Blueprint $table) {
            $table->string('reporting_module', 32)->default('immigration')->after('region')->index();
        });
    }

    public function down(): void
    {
        Schema::table('mobile_submissions', function (Blueprint $table) {
            $table->dropColumn('reporting_module');
        });

        Schema::table('dynamic_forms', function (Blueprint $table) {
            $table->dropColumn('reporting_module');
        });
    }
};
