<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demurrage_charges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('container_id');
            $table->uuid('organization_id');
            $table->string('terminal_name')->nullable();
            $table->string('terminal_firms_code')->nullable();
            $table->timestamp('last_free_day')->nullable();
            $table->integer('free_days')->default(0);
            $table->integer('days_accrued')->default(0);
            $table->decimal('daily_rate', 10, 2)->nullable();
            $table->decimal('estimated_cost', 10, 2)->default(0);
            $table->decimal('actual_cost', 10, 2)->nullable();
            $table->string('currency')->default('USD');
            $table->string('status')->default('accruing');
            $table->boolean('alarm_active')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('container_id')->references('id')->on('containers')->cascadeOnDelete();
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demurrage_charges');
    }
};
