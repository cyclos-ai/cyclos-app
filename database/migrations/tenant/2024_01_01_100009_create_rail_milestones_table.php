<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rail_milestones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('container_id');
            $table->string('carrier_scac')->nullable();
            $table->string('event_type');
            $table->timestamp('event_date');
            $table->string('location')->nullable();
            $table->string('train_id')->nullable();
            $table->timestamp('estimated_arrival')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('container_id')->references('id')->on('containers')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rail_milestones');
    }
};
