<?php

namespace App\Services\Edi;

use App\Domain\Container\Enums\ContainerEventType;
use App\Models\Tenant\Container;
use App\Models\Tenant\ContainerEvent;
use App\Models\Tenant\RailMilestone;
use App\Models\Tenant\RailRamp;
use App\Models\Tenant\RailShipment;
use Illuminate\Support\Facades\Log;

class Edi315Processor
{
    /**
     * Maps EDI 315 status codes to RailShipment statuses and the timestamp
     * column that should be set.
     */
    private const STATUS_MAP = [
        'RL' => ['status' => 'in_transit', 'timestamp_field' => 'departed_at'],
        'RR' => ['status' => 'arrived',    'timestamp_field' => 'arrived_at'],
        'AV' => ['status' => 'available',  'timestamp_field' => 'available_at'],
        'AF' => ['status' => 'available',  'timestamp_field' => 'available_at'],
        'GO' => ['status' => 'picked_up',  'timestamp_field' => 'picked_up_at'],
    ];

    /**
     * Maps EDI 315 status codes to ContainerEventType enum cases.
     */
    private const EVENT_TYPE_MAP = [
        'RL' => ContainerEventType::RAIL_DEPARTURE,
        'RR' => ContainerEventType::RAIL_ARRIVAL,
        'AL' => ContainerEventType::LOADED,
        'DS' => ContainerEventType::DISCHARGED,
        'UV' => ContainerEventType::DISCHARGED,
        'GO' => ContainerEventType::GATE_OUT,
        'GI' => ContainerEventType::GATE_IN,
        'CT' => ContainerEventType::CUSTOMS_HOLD,
        'CD' => ContainerEventType::CUSTOMS_RELEASE,
        'DL' => ContainerEventType::DELIVERED,
        'VD' => ContainerEventType::VESSEL_DEPARTURE,
        'VA' => ContainerEventType::VESSEL_ARRIVAL,
    ];

    public function __construct(private readonly Edi315Parser $parser) {}

    /**
     * Process a parsed EDI 315 data array and update the database.
     *
     * @param  array  $parsed  Output from Edi315Parser::parse()
     * @return array  Summary of what was created/updated
     */
    public function process(array $parsed): array
    {
        $containerNumber = $parsed['container_number'] ?? null;
        $statusCode      = $parsed['status_code'] ?? null;

        $result = [
            'container_number'    => $containerNumber,
            'status_code'         => $statusCode,
            'container_found'     => false,
            'rail_shipment_action'=> null,
            'rail_milestones_created' => 0,
            'container_events_created'=> 0,
            'errors'              => [],
        ];

        if (! $containerNumber) {
            $result['errors'][] = 'No container number in EDI data';
            Log::warning('Edi315Processor: missing container number', ['parsed' => $parsed]);
            return $result;
        }

        $container = Container::where('container_number', strtoupper($containerNumber))->first();

        if (! $container) {
            $result['errors'][] = "Container {$containerNumber} not found";
            Log::info('Edi315Processor: container not found', ['container_number' => $containerNumber]);
            return $result;
        }

        $result['container_found'] = true;

        Log::info('Edi315Processor: processing EDI 315', [
            'container_id'     => $container->id,
            'container_number' => $containerNumber,
            'status_code'      => $statusCode,
            'transaction_id'   => $parsed['transaction_id'] ?? null,
        ]);

        // Update/create RailShipment
        if ($statusCode && isset(self::STATUS_MAP[$statusCode])) {
            $action = $this->updateRailShipment($container, $parsed);
            $result['rail_shipment_action'] = $action;
        }

        // Create RailMilestone records from events array
        foreach ($parsed['events'] as $event) {
            $milestone = $this->createRailMilestone($container, $event, $parsed);
            if ($milestone) {
                $result['rail_milestones_created']++;
            }
        }

        // If no V9 events but we have a primary status, synthesise one milestone
        if (empty($parsed['events']) && $statusCode) {
            $syntheticEvent = [
                'code'        => $statusCode,
                'date'        => $parsed['status_date'],
                'time'        => $parsed['status_time'],
                'carrier'     => $parsed['carrier_scac'],
                'description' => $parsed['status_description'] ?? Edi315Parser::STATUS_CODES[$statusCode] ?? $statusCode,
            ];
            $milestone = $this->createRailMilestone($container, $syntheticEvent, $parsed);
            if ($milestone) {
                $result['rail_milestones_created']++;
            }
        }

        // Create ContainerEvent records from events array
        foreach ($parsed['events'] as $event) {
            $event = $this->createContainerEvent($container, $event, $parsed);
            if ($event) {
                $result['container_events_created']++;
            }
        }

        // Create ContainerEvent for primary status when no V9 events exist
        if (empty($parsed['events']) && $statusCode) {
            $syntheticEvent = [
                'code'        => $statusCode,
                'date'        => $parsed['status_date'],
                'time'        => $parsed['status_time'],
                'carrier'     => $parsed['carrier_scac'],
                'description' => $parsed['status_description'] ?? Edi315Parser::STATUS_CODES[$statusCode] ?? $statusCode,
            ];
            $event = $this->createContainerEvent($container, $syntheticEvent, $parsed);
            if ($event) {
                $result['container_events_created']++;
            }
        }

        return $result;
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

    private function updateRailShipment(Container $container, array $parsed): string
    {
        $statusCode  = $parsed['status_code'];
        $mapping     = self::STATUS_MAP[$statusCode];
        $newStatus   = $mapping['status'];
        $tsField     = $mapping['timestamp_field'];
        $statusDate  = $parsed['status_date'] ? $parsed['status_date'] . ($parsed['status_time'] ? ' ' . $parsed['status_time'] . ':00' : '') : now()->toDateTimeString();

        $shipment = RailShipment::where('container_id', $container->id)
            ->orderByDesc('created_at')
            ->first();

        $rampFromPort = null;
        if (! empty($parsed['ports'])) {
            // For RL (departure) use qualifier R (port of discharge/origin for rail)
            // For RR (arrival) use qualifier L or D
            $portQualifier = in_array($statusCode, ['RL'], true) ? 'R' : 'L';
            $portEntry     = collect($parsed['ports'])->firstWhere('qualifier', $portQualifier);
            if ($portEntry && ! empty($portEntry['code'])) {
                $rampFromPort = RailRamp::where('locode', strtoupper($portEntry['code']))->first()
                    ?? RailRamp::where('code', strtoupper($portEntry['code']))->first();
            }
        }

        $attributes = [
            'status'    => $newStatus,
            $tsField    => $statusDate,
        ];

        if ($shipment) {
            if ($rampFromPort) {
                if ($statusCode === 'RL') {
                    $attributes['origin_ramp_id'] = $rampFromPort->id;
                } elseif ($statusCode === 'RR') {
                    $attributes['destination_ramp_id'] = $rampFromPort->id;
                }
            }
            if ($parsed['carrier_scac']) {
                $attributes['rail_carrier_scac'] = $parsed['carrier_scac'];
            }
            if ($parsed['bill_of_lading']) {
                $attributes['bill_of_lading'] = $parsed['bill_of_lading'];
            }
            $shipment->update($attributes);
            Log::info('Edi315Processor: updated RailShipment', ['id' => $shipment->id, 'status' => $newStatus]);
            return 'updated';
        }

        // Create a new shipment record
        $createData = array_merge($attributes, [
            'container_id'     => $container->id,
            'rail_carrier_scac'=> $parsed['carrier_scac'] ?? null,
            'bill_of_lading'   => $parsed['bill_of_lading'] ?? null,
        ]);

        if ($rampFromPort) {
            if ($statusCode === 'RL') {
                $createData['origin_ramp_id'] = $rampFromPort->id;
            } elseif ($statusCode === 'RR') {
                $createData['destination_ramp_id'] = $rampFromPort->id;
            }
        }

        RailShipment::create($createData);
        Log::info('Edi315Processor: created RailShipment', ['container_id' => $container->id, 'status' => $newStatus]);
        return 'created';
    }

    private function createRailMilestone(Container $container, array $event, array $parsed): ?RailMilestone
    {
        $code = $event['code'] ?? null;
        $date = $event['date'] ?? $parsed['status_date'] ?? null;

        if (! $code || ! $date) {
            return null;
        }

        $eventDateTime = $date . ($event['time'] ? ' ' . $event['time'] . ':00' : '');

        $location = null;
        if (! empty($parsed['ports'])) {
            $port     = collect($parsed['ports'])->first();
            $location = $port['name'] ?? $port['code'] ?? null;
        }

        return RailMilestone::firstOrCreate(
            [
                'container_id'   => $container->id,
                'milestone_code' => $code,
                'milestone_date' => $date,
            ],
            [
                'description' => $event['description'] ?? Edi315Parser::STATUS_CODES[$code] ?? $code,
                'location'    => $location,
                'rail_carrier'=> $event['carrier'] ?? $parsed['carrier_scac'] ?? null,
                'raw_data'    => $parsed,
            ]
        );
    }

    private function createContainerEvent(Container $container, array $event, array $parsed): ?ContainerEvent
    {
        $code = $event['code'] ?? null;
        $date = $event['date'] ?? $parsed['status_date'] ?? null;

        if (! $code || ! $date) {
            return null;
        }

        $eventType = self::EVENT_TYPE_MAP[$code] ?? null;
        if (! $eventType) {
            return null;
        }

        $eventDateTime = $date . ($event['time'] ? ' ' . $event['time'] . ':00' : '');

        // Avoid duplicates
        $exists = ContainerEvent::where('container_id', $container->id)
            ->where('event_type', $eventType->value)
            ->where('event_date', $eventDateTime)
            ->exists();

        if ($exists) {
            return null;
        }

        $location = null;
        if (! empty($parsed['ports'])) {
            $port     = collect($parsed['ports'])->first();
            $location = $port['name'] ?? $port['code'] ?? null;
        }

        return ContainerEvent::create([
            'container_id' => $container->id,
            'event_type'   => $eventType->value,
            'event_date'   => $eventDateTime,
            'location'     => $location,
            'description'  => $event['description'] ?? $eventType->label(),
            'source'       => 'edi_315',
            'raw_data'     => $parsed,
            'created_at'   => now(),
        ]);
    }
}
