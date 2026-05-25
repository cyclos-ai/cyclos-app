<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_columns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('entity_type');
            $table->string('column_name');
            $table->string('display_name');
            $table->string('data_type');
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->string('default_value')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['organization_id', 'entity_type', 'column_name']);
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_columns');
    }
};
