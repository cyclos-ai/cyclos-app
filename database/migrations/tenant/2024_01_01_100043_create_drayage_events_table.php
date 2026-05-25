<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drayage_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('import_drayage_id');
            $table->string('event_type');
            $table->timestamp('event_date');
            $table->string('location')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('notes')->nullable();
            $table->uuid('recorded_by')->nullable();
            $table->string('source')->default('manual'); // manual, gps, api
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('import_drayage_id')->references('id')->on('import_drayage')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drayage_events');
    }
};
