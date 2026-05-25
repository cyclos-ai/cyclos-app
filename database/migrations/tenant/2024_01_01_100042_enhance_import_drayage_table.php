<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_drayage', function (Blueprint $table) {
            $table->string('drayage_type')->default('full')->after('organization_id');
            $table->string('load_type')->nullable()->after('drayage_type');
            $table->string('drayage_status')->default('pending')->after('load_type');
            $table->string('drayage_provider_scac')->nullable()->after('drayage_status');
            $table->string('drayage_provider_name')->nullable()->after('drayage_provider_scac');
            $table->integer('drayage_leg')->default(1)->after('drayage_provider_name');

            // Terminal pickup
            $table->timestamp('terminal_appointment_dt')->nullable();
            $table->timestamp('pickup_appointment_dt')->nullable();
            $table->timestamp('actual_pickup_dt')->nullable();
            $table->timestamp('outgate_dt')->nullable();

            // Delivery
            $table->timestamp('delivery_appointment_dt')->nullable();
            $table->timestamp('actual_arrival_delivery_dt')->nullable();
            $table->timestamp('actual_delivery_dt')->nullable();

            // Empty handling
            $table->timestamp('empty_at_delivery_dt')->nullable();
            $table->timestamp('pickup_empty_dt')->nullable();
            $table->timestamp('empty_return_dt')->nullable();

            // Carrier/driver info
            $table->string('carrier_termination_dt')->nullable();
            $table->string('delivery_order_sent_dt')->nullable();
            $table->uuid('distribution_center_id')->nullable();

            $table->foreign('distribution_center_id')->references('id')->on('distribution_centers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('import_drayage', function (Blueprint $table) {
            $table->dropForeign(['distribution_center_id']);
            $table->dropColumn([
                'drayage_type',
                'load_type',
                'drayage_status',
                'drayage_provider_scac',
                'drayage_provider_name',
                'drayage_leg',
                'terminal_appointment_dt',
                'pickup_appointment_dt',
                'actual_pickup_dt',
                'outgate_dt',
                'delivery_appointment_dt',
                'actual_arrival_delivery_dt',
                'actual_delivery_dt',
                'empty_at_delivery_dt',
                'pickup_empty_dt',
                'empty_return_dt',
                'carrier_termination_dt',
                'delivery_order_sent_dt',
                'distribution_center_id',
            ]);
        });
    }
};
