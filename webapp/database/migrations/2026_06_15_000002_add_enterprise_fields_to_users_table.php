<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('border_post_id')->nullable()->after('is_admin')->constrained('border_posts')->nullOnDelete();
            $table->string('role')->default('border_officer')->after('border_post_id');
            $table->boolean('is_active')->default(true)->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('border_post_id');
            $table->dropColumn(['role', 'is_active']);
        });
    }
};
