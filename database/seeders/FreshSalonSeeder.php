<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Hands a salon a completely empty app: two fresh logins and nothing but the
 * service catalog from database/data/salon_services.csv.
 *
 * DESTRUCTIVE — it clears every invoice, customer, expense, staff member,
 * activity row, service and user first, so the salon starts at invoice 0001
 * with no leftover demo or handover data. Settings (salon name, template,
 * branding) are kept and topped up with any missing defaults.
 *
 *   php artisan db:seed --class=FreshSalonSeeder
 *
 * Override the shared password with SALON_PASSWORD in .env.
 */
class FreshSalonSeeder extends Seeder
{
    public const OWNER_EMAIL = 'owner@wowsalon.com';

    public const STAFF_EMAIL = 'staff@wowsalon.com';

    public const PASSWORD = 'WowSalon@2026';

    /** Catalog source, relative to the project root. */
    public const CATALOG = 'database/data/salon_services.csv';

    /** CSV `gender` column → ServiceCategory audience. */
    private const AUDIENCES = ['women' => 'women', 'men' => 'men', 'unisex' => 'all'];

    /** Cleared in this order so foreign keys are never left dangling. */
    private const WIPE_TABLES = [
        'invoice_items', 'invoices', 'expenses', 'customers', 'activity_log',
        'services', 'service_categories', 'staff_members', 'sessions', 'users',
    ];

    public function run(): void
    {
        $catalog = $this->readCatalog(base_path(self::CATALOG));
        $password = (string) env('SALON_PASSWORD', self::PASSWORD);

        DB::transaction(function () use ($catalog, $password) {
            $this->wipe();
            $this->importCatalog($catalog);
            $this->createLogins($password);
        });

        $this->deleteStoredInvoicePdfs();

        // Fills in any setting the salon has never saved; existing values stay.
        $this->call(SettingsSeeder::class);

        $this->report($password);
    }

    /**
     * Read and validate the catalog up front — a malformed CSV must fail before
     * anything is deleted.
     *
     * @return array<int, array{category: string, audience: string, group: ?string, name: string, price: float, price_max: ?float, is_active: bool}>
     */
    protected function readCatalog(string $path): array
    {
        if (! is_readable($path)) {
            throw new RuntimeException("Service catalog not found at {$path}.");
        }

        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);

            throw new RuntimeException('The service catalog CSV is empty.');
        }

        // Strip a UTF-8 BOM so the first column name still matches.
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
        $header = array_map(fn ($column) => strtolower(trim((string) $column)), $header);

        $expected = ['category', 'gender', 'group', 'service', 'price', 'up_to', 'active'];

        if ($missing = array_diff($expected, $header)) {
            fclose($handle);

            throw new RuntimeException('The service catalog CSV is missing: '.implode(', ', $missing).'.');
        }

        $rows = [];
        $line = 1;

        while (($values = fgetcsv($handle)) !== false) {
            $line++;

            // Skip blank lines rather than importing a nameless service.
            if (count(array_filter($values, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $row = array_combine($header, array_pad(array_slice($values, 0, count($header)), count($header), null));
            $gender = strtolower(trim((string) $row['gender']));
            $name = trim((string) $row['service']);

            if ($name === '' || ! isset(self::AUDIENCES[$gender]) || ! is_numeric($row['price'])) {
                fclose($handle);

                throw new RuntimeException("Line {$line} of the service catalog CSV is invalid: ".implode(',', $values));
            }

            $priceMax = trim((string) $row['up_to']);

            $rows[] = [
                'category' => trim((string) $row['category']),
                'audience' => self::AUDIENCES[$gender],
                'group' => trim((string) $row['group']) ?: null,
                'name' => $name,
                'price' => (float) $row['price'],
                'price_max' => is_numeric($priceMax) ? (float) $priceMax : null,
                'is_active' => filter_var($row['active'] ?? true, FILTER_VALIDATE_BOOL),
            ];
        }

        fclose($handle);

        if ($rows === []) {
            throw new RuntimeException('The service catalog CSV has no service rows.');
        }

        return $rows;
    }

    /** Empty every table that holds salon data. Settings survive. */
    protected function wipe(): void
    {
        foreach (self::WIPE_TABLES as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->delete();
            }
        }
    }

    /** @param  array<int, array<string, mixed>>  $catalog */
    protected function importCatalog(array $catalog): void
    {
        $categories = [];
        $positions = [];

        foreach ($catalog as $row) {
            $key = $row['category'];

            $category = $categories[$key] ??= ServiceCategory::create([
                'name' => $key,
                'audience' => $row['audience'],
                'sort_order' => count($categories) + 1,
                'is_active' => true,
            ]);

            $positions[$key] = ($positions[$key] ?? 0) + 1;

            Service::create([
                'service_category_id' => $category->id,
                'group_name' => $row['group'],
                'name' => $row['name'],
                'price' => $row['price'],
                'price_max' => $row['price_max'],
                'sort_order' => $positions[$key],
                'is_active' => $row['is_active'],
            ]);
        }
    }

    protected function createLogins(string $password): void
    {
        foreach ([
            [self::OWNER_EMAIL, 'Salon Owner', User::ROLE_OWNER],
            [self::STAFF_EMAIL, 'Reception', User::ROLE_STAFF],
        ] as [$email, $name, $role]) {
            User::create([
                'email' => $email,
                'name' => $name,
                'role' => $role,
                'is_active' => true,
                'password' => $password,
            ]);
        }
    }

    /** The invoices are gone, so their rendered PDFs must go too. */
    protected function deleteStoredInvoicePdfs(): void
    {
        $disk = Storage::disk('local');

        foreach ($disk->files('invoices') as $file) {
            $disk->delete($file);
        }
    }

    protected function report(string $password): void
    {
        $this->command?->newLine();
        $this->command?->info(sprintf(
            'Fresh salon ready: %d services in %d categories, no invoices, customers or expenses.',
            Service::count(),
            ServiceCategory::count(),
        ));
        $this->command?->table(['Role', 'Email', 'Password'], [
            ['Owner', self::OWNER_EMAIL, $password],
            ['Receptionist', self::STAFF_EMAIL, $password],
        ]);
        $this->command?->warn('Ask the salon to change both passwords after the first sign-in (Settings → Users).');
    }
}
