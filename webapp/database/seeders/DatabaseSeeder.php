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

        $tenantCountryCode = (string) config('borderreach.tenant_country_code', 'SLE');
        $borderPost = BorderPost::query()
            ->where('country_code', $tenantCountryCode)
            ->where('code', 'BEN-LND')
            ->first()
            ?: BorderPost::query()
                ->where('country_code', $tenantCountryCode)
                ->where('is_active', true)
                ->orderBy('code')
                ->first();

        // Demo credentials are useful locally, but production users must be
        // provisioned intentionally through php artisan admin:create-user.
        if (app()->environment('production')) {
            return;
        }

        $this->call(BorderOfficerSeeder::class);

        $admin = User::query()->updateOrCreate(['email' => (string) config('borderreach.seed.admin_email')], [
            'name' => (string) config('borderreach.seed.admin_name'),
            'password' => Hash::make((string) config('borderreach.seed.admin_password')),
            'country_code' => $tenantCountryCode,
            'border_post_id' => null,
            'role' => config('borderreach.platform_mode') ? User::ROLE_PLATFORM_ADMIN : User::ROLE_HQ_ADMIN,
            'is_admin' => true,
            'is_active' => true,
        ]);

        $this->ensurePersonalTeam($admin);

        if (! $borderPost) {
            return;
        }

        $officer = User::query()->updateOrCreate(['email' => (string) config('borderreach.seed.demo_officer_email')], [
            'name' => "{$borderPost->name} Border Officer",
            'password' => Hash::make((string) config('borderreach.seed.demo_officer_password')),
            'country_code' => $tenantCountryCode,
            'border_post_id' => $borderPost->id,
            'role' => User::ROLE_BORDER_OFFICER,
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
