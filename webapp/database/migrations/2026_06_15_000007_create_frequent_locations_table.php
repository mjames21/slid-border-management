<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('frequent_locations', function (Blueprint $table) {
            $table->id();
            $table->string('country_code', 3)->index();
            $table->string('country_name');
            $table->string('name');
            $table->string('admin_area')->nullable();
            $table->string('category')->nullable();
            $table->text('aliases')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['country_code', 'is_active']);
            $table->unique(['country_code', 'name', 'admin_area'], 'frequent_locations_country_name_area_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('frequent_locations');
    }
};
