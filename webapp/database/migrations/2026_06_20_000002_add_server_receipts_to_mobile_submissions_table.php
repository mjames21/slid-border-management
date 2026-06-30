<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('mobile_submissions', function (Blueprint $table) {
            $table->uuid('server_uid')->nullable()->after('id')->unique();
            $table->timestamp('client_synced_at')->nullable()->after('client_updated_at');
        });

        DB::table('mobile_submissions')
            ->whereNull('server_uid')
            ->orderBy('id')
            ->chunkById(100, function ($submissions): void {
                foreach ($submissions as $submission) {
                    DB::table('mobile_submissions')
                        ->where('id', $submission->id)
                        ->update(['server_uid' => (string) Str::uuid()]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('mobile_submissions', function (Blueprint $table) {
            $table->dropUnique(['server_uid']);
            $table->dropColumn(['server_uid', 'client_synced_at']);
        });
    }
};
