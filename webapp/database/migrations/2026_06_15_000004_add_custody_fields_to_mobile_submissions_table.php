<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('mobile_submissions', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('mobile_device_id')->nullable()->after('user_id')->constrained('mobile_devices')->nullOnDelete();
            $table->foreignId('border_post_id')->nullable()->after('mobile_device_id')->constrained('border_posts')->nullOnDelete();
            $table->string('border_post_code')->nullable()->after('border_post_id');
            $table->string('region')->nullable()->after('border_post_code');
            $table->ipAddress('source_ip')->nullable()->after('rejection_reason');
            $table->text('user_agent')->nullable()->after('source_ip');
            $table->index(['border_post_id', 'received_at']);
            $table->index(['user_id', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::table('mobile_submissions', function (Blueprint $table) {
            $table->dropIndex(['border_post_id', 'received_at']);
            $table->dropIndex(['user_id', 'received_at']);
            $table->dropConstrainedForeignId('user_id');
            $table->dropConstrainedForeignId('mobile_device_id');
            $table->dropConstrainedForeignId('border_post_id');
            $table->dropColumn(['border_post_code', 'region', 'source_ip', 'user_agent']);
        });
    }
};
