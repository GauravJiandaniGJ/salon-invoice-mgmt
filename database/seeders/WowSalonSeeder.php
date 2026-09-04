<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Wow Salon (Gandhidham) handover seed.
 *
 * Creates the two real logins, applies the salon's WhatsApp message and keeps
 * the full service catalog. Run it with salon:go-live to also clear demo data.
 */
class WowSalonSeeder extends Seeder
{
    public const OWNER_EMAIL = 'owner@wowsalon.gandhidham';

    public const STAFF_EMAIL = 'staff@wowsalon.gandhidham';

    public const TEMPLATE = "{greeting} {customer_name} 😃\nThank you for visiting {salon_name}. Your invoice {invoice_number} for ₹{total} is ready:\n{invoice_link}\n\nSee you again soon!";

    public function run(): void
    {
        $password = (string) env('SALON_PASSWORD', 'WowSalon@2026');

        $this->call([SettingsSeeder::class, ServiceCatalogSeeder::class]);

        Setting::set('whatsapp_template', self::TEMPLATE);
        Setting::set('salon_name', 'Wow Salon');
        Setting::flushCache();

        foreach ([
            [self::OWNER_EMAIL, 'Salon Owner', User::ROLE_OWNER],
            [self::STAFF_EMAIL, 'Reception', User::ROLE_STAFF],
        ] as [$email, $name, $role]) {
            $user = User::firstOrNew(['email' => $email]);
            $user->fill(['name' => $name, 'role' => $role, 'is_active' => true, 'password' => $password])->save();
        }

        // Only these two logins should exist on a handed-over salon.
        User::whereNotIn('email', [self::OWNER_EMAIL, self::STAFF_EMAIL])->delete();

        $this->command?->newLine();
        $this->command?->info('Wow Salon accounts ready:');
        $this->command?->table(['Role', 'Email', 'Password'], [
            ['Owner', self::OWNER_EMAIL, $password],
            ['Receptionist', self::STAFF_EMAIL, $password],
        ]);
    }
}
