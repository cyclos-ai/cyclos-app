<?php

namespace Database\Seeders;

use App\Models\Central\Plan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PlanSeeder extends Seeder
{
    /**
     * Seed the central plans table with the base + metered-overage tiers.
     *
     * Run with: php artisan db:seed --class=PlanSeeder
     */
    public function run(): void
    {
        $plans = [
            [
                'name'               => 'Starter',
                'price_monthly'      => 49,
                'included_ai_tokens' => 100000,
                'included_api_calls' => 1000,
            ],
            [
                'name'               => 'Growth',
                'price_monthly'      => 199,
                'included_ai_tokens' => 1000000,
                'included_api_calls' => 10000,
            ],
            [
                'name'               => 'Enterprise',
                'price_monthly'      => 499,
                'included_ai_tokens' => 5000000,
                'included_api_calls' => 100000,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(
                ['name' => $plan['name']],
                [
                    'slug'               => Str::slug($plan['name']),
                    'price_monthly'      => $plan['price_monthly'],
                    'price_yearly'       => $plan['price_monthly'] * 10,
                    'included_ai_tokens' => $plan['included_ai_tokens'],
                    'included_api_calls' => $plan['included_api_calls'],
                    'billing_model'      => 'base_plus_overage',
                    'is_active'          => true,
                ]
            );
        }

        $this->command->info('PlanSeeder: ' . count($plans) . ' plans seeded.');
    }
}
