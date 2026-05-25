<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('container_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('container_id');
            $table->string('event_type')->index();
            $table->timestamp('event_date');
            $table->string('location')->nullable();
            $table->string('location_locode')->nullable();
            $table->string('vessel_name')->nullable();
            $table->string('voyage_number')->nullable();
            $table->text('description')->nullable();
            $table->string('source')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('container_id')->references('id')->on('containers')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('container_events');
    }
};
