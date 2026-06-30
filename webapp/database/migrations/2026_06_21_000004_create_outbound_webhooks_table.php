<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbound_webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('country_code', 3)->index();
            $table->string('name');
            $table->string('endpoint_url', 2048);
            $table->text('signing_secret');
            $table->string('reporting_module', 32)->nullable()->index();
            $table->string('form_id', 120)->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedTinyInteger('timeout_seconds')->default(10);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('country_code')->references('code')->on('countries')->cascadeOnUpdate()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbound_webhooks');
    }
};
