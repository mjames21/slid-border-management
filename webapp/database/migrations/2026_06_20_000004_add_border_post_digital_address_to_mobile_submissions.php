<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('mobile_submissions', function (Blueprint $table) {
            $table->string('border_post_digital_address')->nullable()->after('border_post_code')->index();
        });

        $addresses = DB::table('border_posts')
            ->whereNotNull('digital_address')
            ->pluck('digital_address', 'id');

        DB::table('mobile_submissions')
            ->whereNull('border_post_digital_address')
            ->whereNotNull('border_post_id')
            ->select(['id', 'border_post_id'])
            ->orderBy('id')
            ->chunkById(100, function ($submissions) use ($addresses): void {
                foreach ($submissions as $submission) {
                    $address = $addresses[$submission->border_post_id] ?? null;

                    if (!$address) {
                        continue;
                    }

                    DB::table('mobile_submissions')
                        ->where('id', $submission->id)
                        ->update(['border_post_digital_address' => $address]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('mobile_submissions', function (Blueprint $table) {
            $table->dropIndex(['border_post_digital_address']);
            $table->dropColumn('border_post_digital_address');
        });
    }
};
