<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_usage_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('metric'); // ai_tokens | api_calls_external | api_calls_total
            $table->date('period_date');
            $table->unsignedBigInteger('quantity')->default(0);
            $table->timestamp('reported_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'metric', 'period_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_usage_records');
    }
};
