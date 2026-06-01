<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rail_ramps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('carrier_scac');
            $table->string('city');
            $table->string('state', 2);
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('address')->nullable();
            $table->string('ramp_type')->default('intermodal');
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('carrier_scac');
            $table->index('state');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rail_ramps');
    }
};
