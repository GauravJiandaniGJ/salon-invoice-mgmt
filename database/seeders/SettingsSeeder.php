<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /** Inserts missing keys only; never overwrites values the owner has edited. */
    public function run(): void
    {
        foreach (config('salon.defaults') as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }

        Setting::flushCache();
    }
}
