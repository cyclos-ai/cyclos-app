<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add foreign keys that were deferred from earlier migrations
 * to avoid forward-reference issues (table A references table B
 * that hasn't been created yet).
 */
return new class extends Migration
{
    public function up(): void
    {
        // containers → mbls, bookings, vessels (from 100002)
        Schema::table('containers', function (Blueprint $table) {
            $table->foreign('mbl_id')->references('id')->on('mbls')->nullOnDelete();
            $table->foreign('booking_id')->references('id')->on('bookings')->nullOnDelete();
            $table->foreign('vessel_id')->references('id')->on('vessels')->nullOnDelete();
        });

        // mbls → vessels (from 100003)
        Schema::table('mbls', function (Blueprint $table) {
            $table->foreign('vessel_id')->references('id')->on('vessels')->nullOnDelete();
        });

        // purchase_orders → vendors, factories (from 100020)
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreign('vendor_id')->references('id')->on('vendors')->nullOnDelete();
            $table->foreign('factory_id')->references('id')->on('factories')->nullOnDelete();
        });

        // purchase_order_items → skus (from 100021)
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->foreign('sku_id')->references('id')->on('skus')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('containers', function (Blueprint $table) {
            $table->dropForeign(['mbl_id']);
            $table->dropForeign(['booking_id']);
            $table->dropForeign(['vessel_id']);
        });

        Schema::table('mbls', function (Blueprint $table) {
            $table->dropForeign(['vessel_id']);
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['vendor_id']);
            $table->dropForeign(['factory_id']);
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropForeign(['sku_id']);
        });
    }
};
