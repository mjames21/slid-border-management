<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('email', 'admin@slid.local')
            ->where('is_admin', true)
            ->update(['role' => User::ROLE_PLATFORM_ADMIN]);
    }

    public function down(): void
    {
        DB::table('users')
            ->where('email', 'admin@slid.local')
            ->where('role', User::ROLE_PLATFORM_ADMIN)
            ->update(['role' => User::ROLE_HQ_ADMIN]);
    }
};
