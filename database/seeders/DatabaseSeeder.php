<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SettingsSeeder::class,
            UserSeeder::class,
            ServiceCatalogSeeder::class,
            StaffMemberSeeder::class,
        ]);

        // Demo data only on a developer machine, and only when explicitly asked for.
        if (app()->environment('local') && filter_var(env('SEED_DEMO_DATA', false), FILTER_VALIDATE_BOOL)) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
