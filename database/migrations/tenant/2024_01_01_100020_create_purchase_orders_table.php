<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('po_number')->index();
            $table->uuid('vendor_id')->nullable();
            $table->uuid('factory_id')->nullable();
            $table->string('status')->default('open');
            $table->date('order_date')->nullable();
            $table->date('expected_ship_date')->nullable();
            $table->date('expected_arrival_date')->nullable();
            $table->integer('total_quantity')->default(0);
            $table->decimal('total_value', 12, 2)->default(0);
            $table->string('currency')->default('USD');
            $table->string('ship_to')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('vendor_id')->references('id')->on('vendors')->nullOnDelete();
            $table->foreign('factory_id')->references('id')->on('factories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
