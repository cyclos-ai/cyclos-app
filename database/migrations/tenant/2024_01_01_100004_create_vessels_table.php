<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vessels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('name');
            $table->string('imo_number')->nullable()->index();
            $table->string('mmsi')->nullable();
            $table->string('call_sign')->nullable();
            $table->string('flag')->nullable();
            $table->string('vessel_type')->nullable();
            $table->string('voyage_number')->nullable();
            $table->string('carrier_scac')->nullable();
            $table->decimal('current_latitude', 10, 7)->nullable();
            $table->decimal('current_longitude', 10, 7)->nullable();
            $table->decimal('current_speed', 5, 2)->nullable();
            $table->decimal('current_heading', 5, 2)->nullable();
            $table->timestamp('last_ais_update')->nullable();
            $table->string('pol')->nullable();
            $table->string('pod')->nullable();
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
        Schema::dropIfExists('vessels');
    }
};
