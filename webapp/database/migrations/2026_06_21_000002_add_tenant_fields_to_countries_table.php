<?php

use App\Models\Country;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->string('tenant_slug')->nullable()->after('name')->unique('countries_tenant_slug_unique');
            $table->string('tenant_status', 32)->default(Country::TENANT_STATUS_IMPLEMENTATION)->after('tenant_slug')->index();
            $table->string('deployment_plan', 32)->default(Country::PLAN_PROGRAM)->after('tenant_status')->index();
            $table->string('deployment_type', 32)->default(Country::DEPLOYMENT_HOSTED)->after('deployment_plan');
            $table->string('support_tier', 32)->default('standard')->after('deployment_type');
            $table->string('data_region')->nullable()->after('support_tier');
            $table->string('primary_domain')->nullable()->after('data_region');
        });

        DB::table('countries')->where('code', 'SLE')->update([
            'tenant_slug' => 'sierra-leone',
            'tenant_status' => Country::TENANT_STATUS_ACTIVE,
            'deployment_plan' => Country::PLAN_NATIONAL,
            'deployment_type' => Country::DEPLOYMENT_HOSTED,
            'support_tier' => 'standard',
            'updated_at' => now(),
        ]);

        DB::table('countries')->where('code', 'LBR')->update([
            'tenant_slug' => 'liberia',
            'tenant_status' => Country::TENANT_STATUS_PROSPECT,
            'deployment_plan' => Country::PLAN_EVALUATION,
            'deployment_type' => Country::DEPLOYMENT_HOSTED,
            'support_tier' => 'standard',
            'updated_at' => now(),
        ]);

        DB::table('countries')->where('code', 'GIN')->update([
            'tenant_slug' => 'guinea',
            'tenant_status' => Country::TENANT_STATUS_PROSPECT,
            'deployment_plan' => Country::PLAN_EVALUATION,
            'deployment_type' => Country::DEPLOYMENT_HOSTED,
            'support_tier' => 'standard',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->dropUnique('countries_tenant_slug_unique');
            $table->dropColumn([
                'tenant_slug',
                'tenant_status',
                'deployment_plan',
                'deployment_type',
                'support_tier',
                'data_region',
                'primary_domain',
            ]);
        });
    }
};
