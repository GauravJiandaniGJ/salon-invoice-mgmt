<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            // Who performed this line; defaults to the invoice's staff_member_id at billing time.
            $table->foreignId('staff_member_id')->nullable()->after('service_id')->constrained()->nullOnDelete();
            $table->index(['staff_member_id']);
        });

        Schema::table('staff_members', function (Blueprint $table) {
            $table->decimal('commission_percent', 5, 2)->default(0)->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('staff_member_id');
        });
        Schema::table('staff_members', function (Blueprint $table) {
            $table->dropColumn('commission_percent');
        });
    }
};
