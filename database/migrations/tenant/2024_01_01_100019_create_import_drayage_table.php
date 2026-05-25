<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_drayage', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('container_id')->nullable();
            $table->string('driver_name')->nullable();
            $table->string('truck_number')->nullable();
            $table->string('chassis_number')->nullable();
            $table->string('pickup_location')->nullable();
            $table->string('delivery_location')->nullable();
            $table->timestamp('appointment_date')->nullable();
            $table->timestamp('pickup_date')->nullable();
            $table->timestamp('delivery_date')->nullable();
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('container_id')->references('id')->on('containers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_drayage');
    }
};
