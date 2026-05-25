<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_column_values', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('custom_column_id');
            $table->string('entity_type');
            $table->uuid('entity_id');
            $table->text('value')->nullable();
            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
            $table->foreign('custom_column_id')->references('id')->on('custom_columns')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_column_values');
    }
};
