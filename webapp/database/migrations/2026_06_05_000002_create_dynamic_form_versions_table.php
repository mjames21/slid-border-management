<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('dynamic_form_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dynamic_form_id')->constrained('dynamic_forms')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('source_file_path');
            $table->json('compiled_schema');
            $table->json('source_metadata')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            $table->unique(['dynamic_form_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dynamic_form_versions');
    }
};
