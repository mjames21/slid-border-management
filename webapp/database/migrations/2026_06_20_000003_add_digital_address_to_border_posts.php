<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('border_posts', function (Blueprint $table) {
            $table->string('digital_address')->nullable()->after('code')->unique();
        });

        DB::table('border_posts')
            ->select(['id', 'country_code', 'code'])
            ->orderBy('id')
            ->chunkById(100, function ($posts): void {
                foreach ($posts as $post) {
                    DB::table('border_posts')
                        ->where('id', $post->id)
                        ->update([
                            'digital_address' => self::digitalAddress($post->country_code ?? 'SLE', $post->code),
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('border_posts', function (Blueprint $table) {
            $table->dropUnique(['digital_address']);
            $table->dropColumn('digital_address');
        });
    }

    private static function digitalAddress(string $countryCode, string $postCode): string
    {
        $country = Str::upper(Str::slug($countryCode, ''));
        $code = Str::upper(Str::slug($postCode, '-'));

        return "{$country}-BP-{$code}";
    }
};
