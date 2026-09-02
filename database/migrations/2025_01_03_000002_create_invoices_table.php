<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 20)->unique(); // WS-0001
            $table->string('public_code', 10)->unique(); // [A-Za-z0-9]{10}
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('user_id')->constrained(); // who billed
            $table->foreignId('staff_member_id')->nullable()->constrained()->nullOnDelete(); // who served
            $table->date('invoice_date');
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->string('discount_type', 10)->nullable(); // flat | percent
            $table->decimal('discount_value', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('round_off', 5, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->string('payment_mode', 10)->default('cash'); // cash | upi | card | other
            $table->string('payment_status', 10)->default('paid'); // paid | unpaid
            $table->text('notes')->nullable();
            $table->string('status', 10)->default('issued'); // issued | void
            $table->string('void_reason')->nullable();
            $table->dateTime('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('whatsapp_sent_at')->nullable();
            $table->string('pdf_path')->nullable(); // invoices/WS-0001.pdf on the "local" disk
            $table->timestamps();

            $table->index(['invoice_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
