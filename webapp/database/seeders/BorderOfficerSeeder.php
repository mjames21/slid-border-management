<?php

namespace Database\Seeders;

use App\Models\BorderPost;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class BorderOfficerSeeder extends Seeder
{
    /**
     * Create one demo mobile officer for every active border post.
     *
     * These accounts make end-to-end testing realistic: each officer syncs
     * forms, branding, location catalogs, and map custody metadata through
     * their own assigned border post instead of sharing one generic login.
     */
    public function run(): void
    {
        $password = Hash::make(env('SEED_BORDER_OFFICER_PASSWORD', 'Officer123!'));

        BorderPost::query()
            ->where('is_active', true)
            ->orderBy('country_code')
            ->orderBy('code')
            ->get()
            ->each(function (BorderPost $post) use ($password): void {
                $officer = User::query()->updateOrCreate(
                    ['email' => $this->emailForPost($post)],
                    [
                        'name' => "{$post->name} Border Officer",
                        'password' => $password,
                        'country_code' => $post->country_code ?: 'SLE',
                        'border_post_id' => $post->id,
                        'role' => 'border_officer',
                        'is_admin' => false,
                        'is_active' => true,
                    ]
                );

                $this->ensurePersonalTeam($officer);
            });
    }

    private function emailForPost(BorderPost $post): string
    {
        $code = Str::of($post->code)
            ->lower()
            ->replace('_', '-')
            ->replaceMatches('/[^a-z0-9-]+/', '-')
            ->trim('-');

        return "{$code}.officer@slid.local";
    }

    private function ensurePersonalTeam(User $user): void
    {
        if ($user->current_team_id !== null) {
            return;
        }

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
