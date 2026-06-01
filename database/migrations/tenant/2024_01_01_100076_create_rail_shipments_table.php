<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rail_shipments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('container_id');
            $table->string('rail_carrier_scac');
            $table->string('train_id')->nullable();
            $table->uuid('origin_ramp_id')->nullable();
            $table->uuid('destination_ramp_id')->nullable();
            $table->string('origin_port')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('departed_at')->nullable();
            $table->timestamp('eta')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('available_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->string('bill_of_lading')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('container_id')->references('id')->on('containers')->cascadeOnDelete();
            $table->foreign('origin_ramp_id')->references('id')->on('rail_ramps')->nullOnDelete();
            $table->foreign('destination_ramp_id')->references('id')->on('rail_ramps')->nullOnDelete();

            $table->index('rail_carrier_scac');
            $table->index('status');
            $table->index('container_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rail_shipments');
    }
};
