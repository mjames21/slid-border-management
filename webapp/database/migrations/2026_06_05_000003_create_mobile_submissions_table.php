<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mobile_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('device_id');
            $table->string('local_id');
            $table->string('form_id');
            $table->unsignedInteger('form_version');
            $table->json('answers');
            $table->timestamp('client_created_at')->nullable();
            $table->timestamp('client_updated_at')->nullable();
            $table->timestamp('received_at');
            $table->string('status')->default('accepted');
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->unique(['device_id', 'local_id']);
            $table->index(['form_id', 'form_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_submissions');
    }
};
