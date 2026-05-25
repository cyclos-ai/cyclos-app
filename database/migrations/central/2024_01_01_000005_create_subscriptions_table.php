<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->unsignedInteger('plan_id');
            $table->string('stripe_subscription_id')->nullable();
            $table->string('status');
            $table->string('billing_cycle');
            $table->timestamp('current_period_start');
            $table->timestamp('current_period_end');
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('plan_id')->references('id')->on('plans');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
