<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@gametech.ma'],
            [
                'name' => 'Admin',
                'password' => 'admin12345',
                'email_verified_at' => now(),
            ],
        );
    }
}
