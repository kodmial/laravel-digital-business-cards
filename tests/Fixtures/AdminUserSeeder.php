<?php

namespace DigitalCardKit\Laravel\Tests\Fixtures;

use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        if (User::query()->where('email', 'admin@example.test')->exists()) {
            return;
        }

        User::query()->create([
            'name' => 'Package administrator',
            'email' => 'admin@example.test',
            'password' => bcrypt('password'),
        ]);
    }
}
