<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drayage_invoice_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('drayage_invoice_id');
            $table->string('charge_type');
            $table->string('description')->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('amount', 12, 2);
            $table->string('currency')->default('USD');
            $table->timestamps();

            $table->foreign('drayage_invoice_id')->references('id')->on('drayage_invoices')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drayage_invoice_items');
    }
};
