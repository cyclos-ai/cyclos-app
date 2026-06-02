<?php

namespace App\Services\Tracking;

use App\Models\Tenant\Container;
use App\Models\Tenant\RailMilestone;
use App\Services\Edi\Edi315Parser;
use App\Services\Edi\Edi315Processor;
use Illuminate\Support\Facades\Log;

class RailTrackingService
{
    public function __construct(
        private readonly Edi315Parser    $parser,
        private readonly Edi315Processor $processor,
    ) {}

    /**
     * Process a raw EDI 315 string and update all related records.
     *
     * @param  string  $rawEdi  Raw X12 EDI 315 text
     * @return array   Processing result summary
     */
    public function processEdi315(string $rawEdi): array
    {
        $parsed = $this->parser->parse($rawEdi);

        Log::info('RailTrackingService: processing EDI 315', [
            'container_number' => $parsed['container_number'] ?? null,
            'status_code'      => $parsed['status_code'] ?? null,
            'transaction_id'   => $parsed['transaction_id'] ?? null,
        ]);

        return $this->processor->process($parsed);
    }

    public function pollRailMilestones(Container $container): void
    {
        if (empty($container->rail_carrier)) {
            Log::info('RailTrackingService: No rail carrier configured for container', [
                'container_id' => $container->id,
            ]);
            return;
        }

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
        // EDI 315 messages arrive via the EdiWebhookController (POST /api/v1/edi/315)
        // and are processed through Edi315Processor. This method remains available
        // for pull-based integrations with specific Class I carrier APIs
        // (BNSF, UP, CSX, NS, CN, CP, KCS).
        return [];
    }
}
