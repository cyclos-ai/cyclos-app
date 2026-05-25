<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('report_id');
            $table->string('frequency');
            $table->integer('day_of_week')->nullable();
            $table->integer('day_of_month')->nullable();
            $table->string('time')->default('08:00');
            $table->string('timezone')->default('UTC');
            $table->json('recipients');
            $table->string('format')->default('xlsx');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamp('next_send_at')->nullable();
            $table->timestamps();

            $table->foreign('report_id')->references('id')->on('reports')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_schedules');
    }
};
