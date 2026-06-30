<?php

use App\Models\Country;
use App\Models\DeploymentRequest;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('deployment_requests', function (Blueprint $table) {
            $table->id();
            $table->string('country_name');
            $table->string('agency_name');
            $table->string('contact_name');
            $table->string('contact_email');
            $table->string('contact_phone')->nullable();
            $table->string('contact_role')->nullable();
            $table->string('deployment_plan', 32)->default(Country::PLAN_PROGRAM)->index();
            $table->string('deployment_type', 32)->default(Country::DEPLOYMENT_HOSTED);
            $table->unsignedInteger('expected_posts')->nullable();
            $table->unsignedInteger('expected_users')->nullable();
            $table->json('modules')->nullable();
            $table->text('message')->nullable();
            $table->string('status', 32)->default(DeploymentRequest::STATUS_NEW)->index();
            $table->string('source_ip', 64)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deployment_requests');
    }
};
