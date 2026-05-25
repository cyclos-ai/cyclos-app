<?php

namespace App\Services\Tracking;

use App\Models\Tenant\Container;
use App\Models\Tenant\RailMilestone;
use Illuminate\Support\Facades\Log;

class RailTrackingService
{
    public function pollRailMilestones(Container $container): void
    {
        if (empty($container->rail_carrier)) {
            Log::info('RailTrackingService: No rail carrier configured for container', [
                'container_id' => $container->id,
            ]);
            return;
        }

        // Stub: In production this calls Class I rail carrier APIs
        // (BNSF, UP, CSX, NS, CN, CP, KCS)
        $milestones = $this->fetchRailMilestonesFromCarrier($container);

        foreach ($milestones as $milestoneData) {
            $this->createRailMilestone($container, $milestoneData);
        }
    }

    public function createRailMilestone(Container $container, array $data): RailMilestone
    {
        return RailMilestone::firstOrCreate(
            [
                'container_id'    => $container->id,
                'milestone_code'  => $data['milestone_code'],
                'milestone_date'  => $data['milestone_date'],
            ],
            [
                'description'     => $data['description'] ?? null,
                'location'        => $data['location'] ?? null,
                'city'            => $data['city'] ?? null,
                'state'           => $data['state'] ?? null,
                'rail_carrier'    => $data['rail_carrier'] ?? $container->rail_carrier,
                'raw_data'        => $data['raw_data'] ?? null,
            ]
        );
    }

    private function fetchRailMilestonesFromCarrier(Container $container): array
    {
        // Stub for rail carrier EDI/API integration
        return [];
    }
}
