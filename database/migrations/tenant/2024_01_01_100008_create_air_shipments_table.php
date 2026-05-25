<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('air_shipments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('awb_number')->index();
            $table->string('carrier_code')->nullable();
            $table->string('origin_airport')->nullable();
            $table->string('destination_airport')->nullable();
            $table->integer('pieces')->nullable();
            $table->decimal('weight', 10, 2)->nullable();
            $table->string('weight_unit')->default('KG');
            $table->string('status')->default('pending');
            $table->timestamp('etd')->nullable();
            $table->timestamp('eta')->nullable();
            $table->timestamp('atd')->nullable();
            $table->timestamp('ata')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('air_shipments');
    }
};
