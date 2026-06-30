<?php

namespace Database\Seeders;

use App\Models\BorderPost;
use App\Models\Team;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(BorderPostSeeder::class);
        $this->call(FormTemplateSeeder::class);

        $borderPost = BorderPost::query()
            ->where('code', 'BEN-LND')
            ->firstOrFail();

        // Demo credentials are useful locally, but production users must be
        // provisioned intentionally through php artisan admin:create-user.
        if (app()->environment('production')) {
            return;
        }

        $admin = User::query()->updateOrCreate(['email' => 'admin@slid.local'], [
            'name' => 'SLID Admin',
            'password' => Hash::make(env('SEED_DEMO_ADMIN_PASSWORD', 'Password123!')),
            'country_code' => 'SLE',
            'border_post_id' => null,
            'role' => 'hq_admin',
            'is_admin' => true,
            'is_active' => true,
        ]);

        $this->ensurePersonalTeam($admin);

        $officer = User::query()->updateOrCreate(['email' => 'officer@slid.local'], [
            'name' => 'Bendu Border Officer',
            'password' => Hash::make(env('SEED_DEMO_OFFICER_PASSWORD', 'Officer123!')),
            'country_code' => 'SLE',
            'border_post_id' => $borderPost->id,
            'role' => 'border_officer',
            'is_admin' => false,
            'is_active' => true,
        ]);

        $this->ensurePersonalTeam($officer);
    }

    private function ensurePersonalTeam(User $user): void
    {
        if ($user->current_team_id === null) {
            $team = Team::query()
                ->where('user_id', $user->id)
                ->where('personal_team', true)
                ->first()
                ?: Team::forceCreate([
                    'user_id' => $user->id,
                    'name' => "{$user->name}'s Workspace",
                    'personal_team' => true,
                ]);

            $user->forceFill(['current_team_id' => $team->id])->save();
        }
    }
}
