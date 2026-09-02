<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('SEED_OWNER_PASSWORD', 'password');

        User::firstOrCreate(
            ['email' => 'owner@wowsalon.local'],
            ['name' => 'Salon Owner', 'password' => $password, 'role' => User::ROLE_OWNER]
        );

        User::firstOrCreate(
            ['email' => 'staff@wowsalon.local'],
            ['name' => 'Reception', 'password' => $password, 'role' => User::ROLE_STAFF]
        );
    }
}
