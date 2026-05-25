<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracking_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('request_type');
            $table->string('reference_number')->index();
            $table->string('carrier_scac')->nullable();
            $table->string('status')->default('pending');
            $table->uuid('container_id')->nullable();
            $table->uuid('mbl_id')->nullable();
            $table->uuid('booking_id')->nullable();
            $table->uuid('air_shipment_id')->nullable();
            $table->uuid('requested_by')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('last_polled_at')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('container_id')->references('id')->on('containers')->nullOnDelete();
            $table->foreign('mbl_id')->references('id')->on('mbls')->nullOnDelete();
            $table->foreign('booking_id')->references('id')->on('bookings')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_requests');
    }
};
