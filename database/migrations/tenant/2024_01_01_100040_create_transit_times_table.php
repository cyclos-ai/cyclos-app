<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transit_times', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('container_id')->nullable();
            $table->string('origin_locode');
            $table->string('destination_locode');
            $table->string('carrier_scac')->nullable();
            $table->integer('transit_days');
            $table->integer('port_days')->default(0);
            $table->integer('drayage_days')->default(0);
            $table->integer('total_days');
            $table->timestamp('recorded_at');
            $table->timestamp('created_at')->nullable();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('container_id')->references('id')->on('containers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transit_times');
    }
};
