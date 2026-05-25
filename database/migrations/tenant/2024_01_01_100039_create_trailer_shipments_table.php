<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trailer_shipments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('trailer_number')->index();
            $table->string('carrier_scac')->nullable();
            $table->string('origin')->nullable();
            $table->string('destination')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('pickup_date')->nullable();
            $table->timestamp('delivery_date')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trailer_shipments');
    }
};
