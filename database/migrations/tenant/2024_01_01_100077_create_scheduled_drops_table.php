<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_drops', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('batch_id')->nullable()->index();
            $table->uuid('drayage_carrier_id')->nullable();
            $table->string('drayage_carrier_name');
            $table->string('dc_code')->nullable();
            $table->string('dc_name')->nullable();
            $table->timestamp('vessel_eta')->nullable();
            $table->string('mother_vessel')->nullable();
            $table->date('estimated_drop_date')->nullable();
            $table->uuid('container_id')->nullable();
            $table->string('container_number');
            $table->string('terminal_pickup')->nullable();
            $table->string('ocean_scac')->nullable();
            $table->date('dem_lfd')->nullable();
            $table->string('container_type')->nullable();
            $table->string('requested_stack')->nullable();
            $table->text('dray_notes')->nullable();
            $table->string('status')->default('draft'); // draft | sent
            $table->timestamp('sent_at')->nullable();
            $table->string('sent_to_email')->nullable();
            $table->timestamps();

            $table->foreign('drayage_carrier_id')
                ->references('id')
                ->on('drayage_carriers')
                ->nullOnDelete();

            $table->foreign('container_id')
                ->references('id')
                ->on('containers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_drops');
    }
};
