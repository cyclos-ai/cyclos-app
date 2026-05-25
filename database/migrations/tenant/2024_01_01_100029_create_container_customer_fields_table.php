<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('container_customer_fields', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('container_id');
            $table->uuid('tracking_request_id')->nullable();
            $table->string('field_name');
            $table->text('field_value')->nullable();
            $table->timestamps();

            $table->foreign('container_id')->references('id')->on('containers')->cascadeOnDelete();
            $table->foreign('tracking_request_id')->references('id')->on('tracking_requests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('container_customer_fields');
    }
};
