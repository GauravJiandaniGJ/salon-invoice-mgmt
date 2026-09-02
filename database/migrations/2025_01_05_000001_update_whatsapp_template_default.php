<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Old phase-1 default (long, no partner branding). Rows still equal to it get the new config default. */
    private const OLD_DEFAULT = "{greeting} {customer_name}! 🙏\nThank you for visiting {salon_name}.\n\nYour invoice {invoice_number} for ₹{total} is here:\n{invoice_link}\n\nWe look forward to seeing you again!";

    public function up(): void
    {
        DB::table('settings')
            ->where('key', 'whatsapp_template')
            ->where('value', self::OLD_DEFAULT)
            ->update(['value' => config('salon.defaults.whatsapp_template'), 'updated_at' => now()]);
    }

    public function down(): void
    {
        //
    }
};
