<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customs_milestones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('container_id')->nullable();
            $table->uuid('mbl_id')->nullable();
            $table->string('event_type');
            $table->timestamp('event_date');
            $table->string('hold_type')->nullable();
            $table->text('description')->nullable();
            $table->string('source')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('container_id')->references('id')->on('containers')->nullOnDelete();
            $table->foreign('mbl_id')->references('id')->on('mbls')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customs_milestones');
    }
};
