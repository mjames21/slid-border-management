<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('dynamic_forms', function (Blueprint $table) {
            $table->boolean('is_template')->default(false)->after('title')->index();
            $table->string('template_key')->nullable()->after('is_template')->index();
            $table->text('template_description')->nullable()->after('template_key');
            $table->json('template_summary')->nullable()->after('template_description');
        });

        $templateKeys = [
            'slid_icao_doc_9303_full_inspection' => 'icao_doc_9303_border_movement',
            'slid_border_movement_report' => 'border_movement_basic',
            'border_customs_goods_declaration' => 'customs_goods_declaration',
            'border_security_incident_report' => 'security_incident_report',
            'border_health_quarantine_screening' => 'health_quarantine_screening',
        ];

        foreach ($templateKeys as $formId => $templateKey) {
            DB::table('dynamic_forms')
                ->where('form_id', $formId)
                ->update([
                    'is_template' => true,
                    'template_key' => $templateKey,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('dynamic_forms', function (Blueprint $table) {
            $table->dropColumn(['is_template', 'template_key', 'template_description', 'template_summary']);
        });
    }
};
