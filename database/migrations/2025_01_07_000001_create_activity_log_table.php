<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name');            // kept even if the user is deleted
            $table->string('action', 60);           // invoice.voided, service.price_changed, ...
            $table->string('subject_type', 40)->nullable(); // Invoice, Service, Expense, ...
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_label')->nullable();    // "WS-0063", "Haircut – Men"
            $table->string('description');
            $table->json('changes')->nullable();    // {"price": {"from": 225, "to": 250}}
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->index();

            $table->index(['action', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};
