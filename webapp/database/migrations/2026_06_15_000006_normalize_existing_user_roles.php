<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('users')->where('is_admin', true)->update([
            'role' => 'hq_admin',
            'is_active' => true,
        ]);
    }

    public function down(): void
    {
        DB::table('users')->where('is_admin', true)->update([
            'role' => 'border_officer',
        ]);
    }
};
