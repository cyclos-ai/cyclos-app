<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ocean_invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('invoice_number')->index();
            $table->string('carrier_scac')->nullable();
            $table->uuid('mbl_id')->nullable();
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('currency')->default('USD');
            $table->string('status')->default('pending');
            $table->date('payment_date')->nullable();
            $table->string('payment_reference')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('mbl_id')->references('id')->on('mbls')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ocean_invoices');
    }
};
