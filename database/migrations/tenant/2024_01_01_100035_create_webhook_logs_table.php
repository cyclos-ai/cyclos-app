<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('webhook_id');
            $table->string('event_type');
            $table->json('payload');
            $table->integer('response_status')->nullable();
            $table->text('response_body')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('webhook_id')->references('id')->on('webhooks')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
    }
};
