<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('dynamic_forms', function (Blueprint $table) {
            $table->dropUnique(['form_id']);
            $table->unique(['country_code', 'form_id'], 'dynamic_forms_country_form_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('dynamic_forms', function (Blueprint $table) {
            $table->dropUnique('dynamic_forms_country_form_id_unique');
            $table->unique('form_id');
        });
    }
};
