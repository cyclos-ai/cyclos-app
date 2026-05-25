<?php

namespace App\Services\Tracking;

use App\Domain\Container\Enums\ContainerEventType;
use App\Domain\Container\Enums\ContainerStatus;
use App\Events\Container\ContainerStatusChanged;
use App\Events\Tracking\TrackingRequestCreated;
use App\Events\Tracking\TrackingRequestFailed;
use App\Exceptions\TrackingException;
use App\Models\Tenant\Container;
use App\Models\Tenant\ContainerEvent;
use App\Models\Tenant\TrackingRequest;
use App\Services\Tracking\Carriers\CarrierRegistry;
use App\Services\Tracking\Carriers\CarrierTrackerFactory;
use App\Services\Tracking\Carriers\CarrierTrackingEvent;
use App\Services\Tracking\Carriers\CarrierTrackingResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ContainerTrackingService implements ContainerTrackingServiceInterface
{
    private const VALID_TRANSITIONS = [
        ContainerStatus::NOT_TRACKING->value       => [ContainerStatus::AT_ORIGIN->value, ContainerStatus::LOADED_ON_VESSEL->value, ContainerStatus::ON_WATER->value],
        ContainerStatus::AT_ORIGIN->value          => [ContainerStatus::LOADED_ON_VESSEL->value],
        ContainerStatus::LOADED_ON_VESSEL->value   => [ContainerStatus::ON_WATER->value, ContainerStatus::AWAITING_DISCHARGE->value],
        ContainerStatus::ON_WATER->value           => [ContainerStatus::AWAITING_DISCHARGE->value, ContainerStatus::AT_OCEAN_TERMINAL->value],
        ContainerStatus::AWAITING_DISCHARGE->value => [ContainerStatus::AT_OCEAN_TERMINAL->value],
        ContainerStatus::AT_OCEAN_TERMINAL->value  => [ContainerStatus::ON_RAIL->value, ContainerStatus::OUT_FOR_DELIVERY->value],
        ContainerStatus::ON_RAIL->value            => [ContainerStatus::ARRIVED_AT_RAIL_TERMINAL->value, ContainerStatus::OUT_FOR_DELIVERY->value],
        ContainerStatus::ARRIVED_AT_RAIL_TERMINAL->value => [ContainerStatus::OUT_FOR_DELIVERY->value],
        ContainerStatus::OUT_FOR_DELIVERY->value   => [ContainerStatus::EMPTY_RETURNED->value, ContainerStatus::DROPPED->value],
        ContainerStatus::EMPTY_RETURNED->value     => [],
        ContainerStatus::DROPPED->value            => [],
    ];

    public function __construct(
        private readonly CarrierTrackerFactory $trackerFactory,
    ) {}

    public function createTrackingRequest(array $data): TrackingRequest
    {
        $reference = $data['reference'] ?? ($data['mbl_number'] ?? ($data['booking_number'] ?? null));

        if (empty($reference)) {
            throw new TrackingException('A tracking reference (MBL, booking, or container number) is required.');
        }

        $carrierScac = $data['carrier_scac'] ?? null;

        if (empty($carrierScac) && !empty($data['mbl_number'])) {
            $carrierScac = $this->detectCarrierFromMBL($data['mbl_number']);
        }

        if (empty($carrierScac) && !empty($data['container_number'])) {
            $carrierScac = CarrierRegistry::detectFromContainerPrefix($data['container_number']);
        }

        /** @var TrackingRequest $trackingRequest */
        $trackingRequest = TrackingRequest::create([
            'organization_id' => $data['organization_id'],
            'container_id'    => $data['container_id'] ?? null,
            'mbl_id'          => $data['mbl_id'] ?? null,
            'booking_id'      => $data['booking_id'] ?? null,
            'air_shipment_id' => $data['air_shipment_id'] ?? null,
            'carrier_scac'    => $carrierScac,
            'reference_type'  => $data['reference_type'] ?? 'mbl',
            'reference_value' => $reference,
            'status'          => 'pending',
            'last_polled_at'  => null,
        ]);

        event(new TrackingRequestCreated($trackingRequest));

        return $trackingRequest;
    }

    public function pollCarrierUpdates(TrackingRequest $request): void
    {
        try {
            $request->update(['status' => 'active', 'last_polled_at' => now()]);

            $carrierResponse = $this->callCarrierApi($request);

            if ($carrierResponse === null) {
                Log::info('ContainerTrackingService: No carrier response', [
                    'tracking_request_id' => $request->id,
                    'carrier_scac'        => $request->carrier_scac,
                ]);
                return;
            }

            if (!$carrierResponse->success) {
                Log::warning('ContainerTrackingService: Carrier returned failure', [
                    'tracking_request_id' => $request->id,
                    'carrier_scac'        => $request->carrier_scac,
                    'error'               => $carrierResponse->errorMessage,
                ]);
                return;
            }

            $this->processCarrierResponse($request, $carrierResponse);

        } catch (\Throwable $e) {
            Log::error('ContainerTrackingService: pollCarrierUpdates failed', [
                'tracking_request_id' => $request->id,
                'error'               => $e->getMessage(),
            ]);

            $request->update(['status' => 'failed']);

            event(new TrackingRequestFailed($request, $e->getMessage()));

            throw new TrackingException('Carrier poll failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function updateContainerStatus(Container $container, ContainerStatus $newStatus, array $eventData = []): void
    {
        $previousStatus = $container->status;

        if ($previousStatus === $newStatus) {
            return;
        }

        $allowedTransitions = self::VALID_TRANSITIONS[$previousStatus->value] ?? [];

        if (!in_array($newStatus->value, $allowedTransitions, true)) {
            Log::warning('ContainerTrackingService: Invalid status transition attempted', [
                'container_id' => $container->id,
                'from'         => $previousStatus->value,
                'to'           => $newStatus->value,
            ]);
            throw new TrackingException(
                "Invalid status transition from {$previousStatus->value} to {$newStatus->value}"
            );
        }

        $container->update(['status' => $newStatus]);

        ContainerEvent::create([
            'container_id' => $container->id,
            'event_type'   => ContainerEventType::STATUS_CHANGE ?? ContainerEventType::MANUAL_UPDATE,
            'description'  => "Status changed from {$previousStatus->label()} to {$newStatus->label()}",
            'event_date'   => now(),
            'location'     => $eventData['location'] ?? null,
            'raw_data'     => $eventData,
            'created_at'   => now(),
        ]);

        event(new ContainerStatusChanged($container, $previousStatus, $newStatus, $eventData));
    }

    public function getContainerTimeline(Container $container): Collection
    {
        return $container->events()
            ->orderBy('event_date', 'asc')
            ->get();
    }

    public function detectCarrierFromMBL(string $mblNumber): ?string
    {
        return CarrierRegistry::detectFromMBL($mblNumber);
    }

    /**
     * Initiate full origin-to-destination tracking for a container.
     * Resolves the carrier, calls the API, and persists all returned events.
     */
    public function trackContainerFromOrigin(Container $container): CarrierTrackingResponse
    {
        $scac = $container->carrier_scac
            ?? CarrierRegistry::detectFromMBL($container->mbl_number ?? '')
            ?? CarrierRegistry::detectFromContainerPrefix($container->container_number ?? '');

        if (empty($scac)) {
            Log::warning('ContainerTrackingService: Cannot determine carrier SCAC for full tracking', [
                'container_id' => $container->id,
            ]);
            return \App\Services\Tracking\Carriers\CarrierTrackingResponse::failure(
                'Unable to determine carrier from container or MBL number.'
            );
        }

        $tracker  = $this->trackerFactory->make($scac);
        $response = null;

        if (!empty($container->container_number)) {
            $response = $tracker->trackByContainer($container->container_number);
        } elseif (!empty($container->mbl_number)) {
            $response = $tracker->trackByMBL($container->mbl_number);
        } else {
            return \App\Services\Tracking\Carriers\CarrierTrackingResponse::failure(
                'Container has neither a container number nor an MBL number to track by.'
            );
        }

        if ($response->success) {
            $this->persistCarrierEvents($container, $response);
        }

        return $response;
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

    private function callCarrierApi(TrackingRequest $request): ?CarrierTrackingResponse
    {
        $scac = $request->carrier_scac;

        if (empty($scac)) {
            return null;
        }

        $tracker = $this->trackerFactory->make($scac);

        return match ($request->reference_type) {
            'mbl'       => $tracker->trackByMBL($request->reference_value),
            'container' => $tracker->trackByContainer($request->reference_value),
            'booking'   => $tracker->trackByBooking($request->reference_value),
            default     => $tracker->trackByMBL($request->reference_value),
        };
    }

    private function processCarrierResponse(TrackingRequest $request, CarrierTrackingResponse $response): void
    {
        $container = $request->container;

        if ($container === null) {
            return;
        }

        // Update vessel / voyage info from the latest response
        $updates = array_filter([
            'vessel_name'    => $response->vesselName,
            'voyage_number'  => $response->voyageNumber,
            'pol'            => $response->pol,
            'pod'            => $response->pod,
            'eta'            => $response->eta,
            'ata'            => $response->ata,
            'etd'            => $response->etd,
            'atd'            => $response->atd,
        ]);

        if (!empty($updates)) {
            $container->update($updates);
        }

        $this->persistCarrierEvents($container, $response);
    }

    private function persistCarrierEvents(Container $container, CarrierTrackingResponse $response): void
    {
        foreach ($response->events as $carrierEvent) {
            /** @var CarrierTrackingEvent $carrierEvent */
            $eventType = ContainerEventType::tryFrom($carrierEvent->eventType)
                ?? ContainerEventType::MANUAL_UPDATE;

            // Avoid duplicate events (same type + date + location)
            $exists = ContainerEvent::where('container_id', $container->id)
                ->where('event_type', $eventType->value)
                ->where('event_date', $carrierEvent->eventDate)
                ->exists();

            if ($exists) {
                continue;
            }

            ContainerEvent::create([
                'container_id' => $container->id,
                'event_type'   => $eventType->value,
                'description'  => $carrierEvent->description ?? $eventType->label(),
                'event_date'   => $carrierEvent->eventDate,
                'location'     => $carrierEvent->location,
                'vessel'       => $carrierEvent->vessel,
                'voyage'       => $carrierEvent->voyage,
                'locode'       => $carrierEvent->locode,
                'raw_data'     => $carrierEvent->toArray(),
            ]);

            // Attempt to advance container status based on the event
            $newStatus = $this->eventTypeToStatus($eventType);

            if ($newStatus !== null) {
                try {
                    $this->updateContainerStatus($container, $newStatus, [
                        'location' => $carrierEvent->location,
                        'vessel'   => $carrierEvent->vessel,
                        'voyage'   => $carrierEvent->voyage,
                    ]);
                } catch (\Throwable) {
                    // Invalid transition – container may already be at a later status; skip silently
                }
            }
        }
    }

    /**
     * Map a ContainerEventType to the ContainerStatus it implies, if any.
     */
    private function eventTypeToStatus(ContainerEventType $eventType): ?ContainerStatus
    {
        return match ($eventType) {
            ContainerEventType::GATE_IN          => ContainerStatus::AT_ORIGIN,
            ContainerEventType::LOADED           => ContainerStatus::LOADED_ON_VESSEL,
            ContainerEventType::VESSEL_DEPARTURE => ContainerStatus::ON_WATER,
            ContainerEventType::VESSEL_ARRIVAL   => ContainerStatus::AWAITING_DISCHARGE,
            ContainerEventType::DISCHARGED       => ContainerStatus::AT_OCEAN_TERMINAL,
            ContainerEventType::RAIL_DEPARTURE   => ContainerStatus::ON_RAIL,
            ContainerEventType::RAIL_ARRIVAL     => ContainerStatus::ARRIVED_AT_RAIL_TERMINAL,
            ContainerEventType::OUT_FOR_DELIVERY => ContainerStatus::OUT_FOR_DELIVERY,
            ContainerEventType::EMPTY_RETURN     => ContainerStatus::EMPTY_RETURNED,
            default                              => null,
        };
    }
}
