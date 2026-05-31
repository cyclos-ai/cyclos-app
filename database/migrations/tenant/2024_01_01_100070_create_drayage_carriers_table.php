<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drayage_carriers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('company_name');
            $table->string('scac', 10)->index();
            $table->string('usdot', 20)->nullable();
            $table->string('mc_number', 20)->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 2)->nullable();
            $table->string('zip', 10)->nullable();
            $table->string('status')->default('active'); // active, suspended, inactive
            $table->integer('fleet_size')->nullable();
            $table->json('equipment_types')->nullable(); // ['flatbed','container','reefer']
            $table->json('service_areas')->nullable(); // ['FL','GA','SC']
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->unique(['organization_id', 'scac']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drayage_carriers');
    }
};
