<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ocean_invoice_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('ocean_invoice_id');
            $table->uuid('container_id')->nullable();
            $table->string('charge_type');
            $table->string('description')->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('amount', 12, 2);
            $table->string('currency')->default('USD');
            $table->timestamps();

            $table->foreign('ocean_invoice_id')->references('id')->on('ocean_invoices')->cascadeOnDelete();
            $table->foreign('container_id')->references('id')->on('containers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ocean_invoice_items');
    }
};
