<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdministratorSeeder extends Seeder
{
    public function run(): void
    {
        $name = trim((string) config('administrator.name'));
        $email = trim((string) config('administrator.email'));
        $password = (string) config('administrator.password');

        if ($email === '' || $password === '') {
            return;
        }

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name !== '' ? $name : 'Warehouse Administrator',
                'password' => $password,
            ],
        );
    }
}
