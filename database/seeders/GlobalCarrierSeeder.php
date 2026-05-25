<?php

namespace Database\Seeders;

use App\Services\Tracking\Carriers\CarrierRegistry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GlobalCarrierSeeder extends Seeder
{
    /**
     * Seed the global_carriers table with all carriers from the registry.
     *
     * Run with: php artisan db:seed --class=GlobalCarrierSeeder
     */
    public function run(): void
    {
        $now = now();

        foreach (CarrierRegistry::getAllCarriers() as $scac => $carrier) {
            DB::table('global_carriers')->upsert(
                [
                    'scac'             => $scac,
                    'name'             => $carrier['name'],
                    'group_name'       => $carrier['group'],
                    'api_type'         => $carrier['api_type'],
                    'tracking_url'     => $carrier['tracking_url'],
                    'website'          => $carrier['website'],
                    'aliases'          => json_encode($carrier['aliases']),
                    'supports_tracking'=> $carrier['tracking_url'] !== null ? 1 : 0,
                    'is_active'        => 1,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ],
                ['scac'],
                ['name', 'group_name', 'api_type', 'tracking_url', 'website', 'aliases', 'supports_tracking', 'updated_at']
            );
        }

        $this->command->info('GlobalCarrierSeeder: ' . count(CarrierRegistry::getAllCarriers()) . ' carriers seeded.');
    }
}
