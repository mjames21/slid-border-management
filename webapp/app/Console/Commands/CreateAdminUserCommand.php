<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdminUserCommand extends Command
{
    protected $signature = 'admin:create-user {name?} {email?} {--password=} {--country-admin : Create a country workspace administrator instead of a platform administrator}';
    protected $description = 'Create an administrator user for the BorderReach backend';

    public function handle(): int
    {
        $name = $this->argument('name') ?: $this->ask('Name');
        $email = $this->argument('email') ?: $this->ask('Email');
        $password = $this->option('password') ?: $this->secret('Password');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', Password::min(10)->mixedCase()->numbers()],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return self::FAILURE;
        }

        $role = $this->option('country-admin') ? User::ROLE_HQ_ADMIN : User::ROLE_PLATFORM_ADMIN;

        $user = DB::transaction(function () use ($name, $email, $password, $role) {
            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'is_admin' => true,
                'role' => $role,
                'is_active' => true,
            ]);

            $team = Team::forceCreate([
                'user_id' => $user->id,
                'name' => 'SLID Administration',
                'personal_team' => true,
            ]);

            $user->forceFill(['current_team_id' => $team->id])->save();

            return $user;
        });

        $this->info("Administrator created: {$user->email} ({$user->role})");
        return self::SUCCESS;
    }
}
