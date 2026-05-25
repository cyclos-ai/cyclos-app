<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transshipments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('container_id');
            $table->uuid('from_vessel_id')->nullable();
            $table->uuid('to_vessel_id')->nullable();
            $table->string('transshipment_port')->nullable();
            $table->string('transshipment_locode')->nullable();
            $table->timestamp('discharge_date')->nullable();
            $table->timestamp('load_date')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->foreign('container_id')->references('id')->on('containers')->cascadeOnDelete();
            $table->foreign('from_vessel_id')->references('id')->on('vessels')->nullOnDelete();
            $table->foreign('to_vessel_id')->references('id')->on('vessels')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transshipments');
    }
};
