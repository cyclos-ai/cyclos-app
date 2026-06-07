<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->table('plans', function (Blueprint $table) {
            $table->string('stripe_product_id')->nullable()->after('features');
            $table->string('stripe_price_monthly_id')->nullable()->after('stripe_product_id');
            $table->string('stripe_price_yearly_id')->nullable()->after('stripe_price_monthly_id');
            $table->string('stripe_meter_price_tokens_id')->nullable()->after('stripe_price_yearly_id');
            $table->string('stripe_meter_price_calls_id')->nullable()->after('stripe_meter_price_tokens_id');
            $table->unsignedBigInteger('included_ai_tokens')->default(0)->after('stripe_meter_price_calls_id');
            $table->unsignedBigInteger('included_api_calls')->default(0)->after('included_ai_tokens');
            $table->string('billing_model')->default('base_plus_overage')->after('included_api_calls');
        });

        Schema::connection('central')->table('tenants', function (Blueprint $table) {
            $table->string('stripe_customer_id')->nullable()->after('plan_id');
            $table->string('billing_email')->nullable()->after('stripe_customer_id');
        });

        Schema::connection('central')->table('subscriptions', function (Blueprint $table) {
            $table->string('stripe_customer_id')->nullable()->after('stripe_subscription_id');
            $table->string('stripe_price_id')->nullable()->after('stripe_customer_id');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('plans', function (Blueprint $table) {
            $table->dropColumn([
                'stripe_product_id',
                'stripe_price_monthly_id',
                'stripe_price_yearly_id',
                'stripe_meter_price_tokens_id',
                'stripe_meter_price_calls_id',
                'included_ai_tokens',
                'included_api_calls',
                'billing_model',
            ]);
        });

        Schema::connection('central')->table('tenants', function (Blueprint $table) {
            $table->dropColumn(['stripe_customer_id', 'billing_email']);
        });

        Schema::connection('central')->table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['stripe_customer_id', 'stripe_price_id']);
        });
    }
};
