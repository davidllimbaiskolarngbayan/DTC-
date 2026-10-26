<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateAdmin extends Command
{
    protected $signature = 'dtc:create-admin {email : Login email} {--name=Admin : Display name}';

    protected $description = 'Create an admin user (password is prompted, never passed as an argument)';

    public function handle(): int
    {
        $password = (string) $this->secret('Password (min 12 characters)');

        $validator = Validator::make(
            ['email' => $this->argument('email'), 'password' => $password],
            ['email' => ['required', 'email', 'unique:users,email'], 'password' => ['required', 'min:12']]
        );

        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        User::create([
            'name' => $this->option('name'),
            'email' => $this->argument('email'),
            'password' => Hash::make($password),
        ]);

        $this->info('Admin created.');

        return self::SUCCESS;
    }
}
