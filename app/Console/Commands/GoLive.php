<?php

namespace App\Console\Commands;

use App\Models\Activity;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Service;
use App\Models\Setting;
use App\Models\StaffMember;
use App\Models\User;
use App\Support\PhoneNumber;
use Database\Seeders\ServiceCatalogSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Prepares a fresh salon for real use: clears any demo billing data, seeds the
 * full service catalog and creates the owner + receptionist logins.
 *
 * Safe to run on a server that already has the demo data seeded.
 */
class GoLive extends Command
{
    protected $signature = 'salon:go-live
        {--owner-email= : Owner login email}
        {--owner-name= : Owner full name}
        {--staff-email= : Receptionist login email}
        {--staff-name= : Receptionist full name}
        {--password= : Password for both accounts (generated when omitted)}
        {--salon-name= : Salon name shown on invoices}
        {--phone= : Salon phone shown on invoices}
        {--address= : Salon address shown on invoices}
        {--app-url= : Public https URL, used in WhatsApp links}
        {--barbers= : Comma separated staff names, e.g. "Raj:10,Priya:12.5"}
        {--keep-data : Keep existing invoices, customers and expenses}
        {--force : Do not ask for confirmation}';

    protected $description = 'Set up a salon for real use: clear demo data, seed services, create logins';

    public function handle(): int
    {
        $ownerEmail = $this->option('owner-email') ?: $this->ask('Owner email');
        $ownerName = $this->option('owner-name') ?: $this->ask('Owner name', 'Salon Owner');
        $staffEmail = $this->option('staff-email') ?: $this->ask('Receptionist email');
        $staffName = $this->option('staff-name') ?: $this->ask('Receptionist name', 'Reception');
        $password = $this->option('password') ?: Str::password(12, symbols: false);

        foreach (['owner' => $ownerEmail, 'receptionist' => $staffEmail] as $who => $email) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->error("The {$who} email is not a valid address: {$email}");

                return self::FAILURE;
            }
        }

        if (strlen($password) < 8) {
            $this->error('The password must be at least 8 characters.');

            return self::FAILURE;
        }

        $wipe = ! $this->option('keep-data');
        $counts = ['invoices' => Invoice::count(), 'customers' => Customer::count(), 'expenses' => Expense::count()];

        if ($wipe && array_sum($counts) > 0 && ! $this->option('force')) {
            $this->warn(sprintf(
                'This deletes %d invoices, %d customers and %d expenses.',
                $counts['invoices'], $counts['customers'], $counts['expenses'],
            ));

            if (! $this->confirm('Continue?', false)) {
                $this->info('Nothing changed.');

                return self::SUCCESS;
            }
        }

        if ($wipe) {
            $this->wipeBillingData();
            $this->line('  Cleared demo invoices, customers and expenses.');
        }

        $this->callSilent('db:seed', ['--class' => SettingsSeeder::class, '--force' => true]);
        $this->callSilent('db:seed', ['--class' => ServiceCatalogSeeder::class, '--force' => true]);
        $this->line('  Service catalog ready: '.Service::count().' services.');

        $this->applySettings();
        $this->applyBarbers();

        $owner = $this->upsertUser($ownerEmail, $ownerName, User::ROLE_OWNER, $password);
        $staff = $this->upsertUser($staffEmail, $staffName, User::ROLE_STAFF, $password);

        // Demo logins must never survive into a live salon.
        User::whereIn('email', ['owner@wowsalon.local', 'staff@wowsalon.local'])
            ->whereNotIn('id', [$owner->id, $staff->id])
            ->delete();

        Activity::log('settings.updated', 'Salon prepared for live use', null, null, $ownerName);

        $this->newLine();
        $this->info('Ready to use. Send these details to the salon:');
        $this->newLine();
        $this->table(['Role', 'Email', 'Password'], [
            ['Owner', $owner->email, $password],
            ['Receptionist', $staff->email, $password],
        ]);
        $this->line('  Sign in at: '.rtrim((string) Setting::get('app_url'), '/').'/login');
        $this->newLine();
        $this->warn('Ask them to change both passwords after the first sign-in (Settings → Users).');

        return self::SUCCESS;
    }

    /** Remove every trace of billing data, including soft-deleted rows and stored PDFs. */
    protected function wipeBillingData(): void
    {
        DB::transaction(function () {
            InvoiceItem::query()->forceDelete();
            Invoice::query()->forceDelete();
            Expense::withTrashed()->forceDelete();
            Customer::withTrashed()->forceDelete();
            Activity::query()->delete();
        });

        $disk = Storage::disk('local');

        foreach ($disk->files('invoices') as $file) {
            $disk->delete($file);
        }
    }

    protected function applySettings(): void
    {
        $map = [
            'salon_name' => $this->option('salon-name'),
            'salon_phone' => $this->option('phone'),
            'salon_address' => $this->option('address'),
            'app_url' => $this->option('app-url'),
        ];

        foreach (array_filter($map) as $key => $value) {
            if ($key === 'salon_phone') {
                try {
                    $value = PhoneNumber::display(PhoneNumber::normalise($value));
                } catch (InvalidArgumentException) {
                    $this->warn("  Kept the phone number as typed: {$value}");
                }
            }

            if ($key === 'app_url') {
                $value = rtrim($value, '/');
            }

            Setting::set($key, $value);
        }

        Setting::flushCache();
    }

    /** --barbers="Raj:10,Priya:12.5" — name with an optional commission percent. */
    protected function applyBarbers(): void
    {
        $input = (string) $this->option('barbers');

        if ($input === '') {
            return;
        }

        foreach (array_filter(array_map('trim', explode(',', $input))) as $entry) {
            [$name, $percent] = array_pad(explode(':', $entry, 2), 2, 0);

            StaffMember::updateOrCreate(
                ['name' => trim($name)],
                ['commission_percent' => (float) $percent, 'is_active' => true],
            );
        }

        $this->line('  Staff members: '.StaffMember::where('is_active', true)->pluck('name')->implode(', '));
    }

    protected function upsertUser(string $email, string $name, string $role, string $password): User
    {
        $user = User::withoutGlobalScopes()->firstOrNew(['email' => $email]);

        $user->fill([
            'name' => $name,
            'role' => $role,
            'is_active' => true,
            'password' => $password,
        ])->save();

        return $user;
    }
}
