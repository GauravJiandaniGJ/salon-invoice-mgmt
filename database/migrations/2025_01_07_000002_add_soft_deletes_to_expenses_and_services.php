<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nothing a salon bills or spends should vanish from the database; deletes only hide.
        Schema::table('expenses', fn (Blueprint $t) => $t->softDeletes());
        Schema::table('services', fn (Blueprint $t) => $t->softDeletes());
        Schema::table('service_categories', fn (Blueprint $t) => $t->softDeletes());
        Schema::table('customers', fn (Blueprint $t) => $t->softDeletes());
    }

    public function down(): void
    {
        Schema::table('expenses', fn (Blueprint $t) => $t->dropSoftDeletes());
        Schema::table('services', fn (Blueprint $t) => $t->dropSoftDeletes());
        Schema::table('service_categories', fn (Blueprint $t) => $t->dropSoftDeletes());
        Schema::table('customers', fn (Blueprint $t) => $t->dropSoftDeletes());
    }
};
