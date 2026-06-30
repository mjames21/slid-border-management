<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outbound_webhook_id')->constrained('outbound_webhooks')->cascadeOnDelete();
            $table->foreignId('mobile_submission_id')->constrained('mobile_submissions')->cascadeOnDelete();
            $table->string('event_type', 80)->default('submission.accepted');
            $table->string('status', 32)->default('pending')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedSmallInteger('last_status_code')->nullable();
            $table->string('payload_sha256', 64)->nullable();
            $table->text('response_body')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamps();

            $table->unique(['outbound_webhook_id', 'mobile_submission_id'], 'webhook_deliveries_unique_submission');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
    }
};
