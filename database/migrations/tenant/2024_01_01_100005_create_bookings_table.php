<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('booking_number')->index();
            $table->string('carrier_scac')->nullable();
            $table->uuid('vessel_id')->nullable();
            $table->string('voyage_number')->nullable();
            $table->string('por')->nullable();
            $table->string('pol')->nullable();
            $table->string('pod')->nullable();
            $table->string('del')->nullable();
            $table->integer('container_count')->default(0);
            $table->string('commodity')->nullable();
            $table->decimal('weight', 10, 2)->nullable();
            $table->string('weight_unit')->default('KG');
            $table->timestamp('cut_off_date')->nullable();
            $table->timestamp('si_cut_off')->nullable();
            $table->timestamp('vgm_cut_off')->nullable();
            $table->timestamp('etd')->nullable();
            $table->timestamp('eta')->nullable();
            $table->string('status')->default('pending');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('vessel_id')->references('id')->on('vessels')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
