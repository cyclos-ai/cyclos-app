<?php

namespace App\Services\Tracking;

use App\Events\Tracking\TrackingRequestCreated;
use App\Models\Tenant\AirShipment;
use App\Models\Tenant\TrackingRequest;
use Illuminate\Support\Facades\Log;

class AirTrackingService
{
    public function createAirTracking(array $data): TrackingRequest
    {
        $airShipment = AirShipment::findOrFail($data['air_shipment_id']);

        /** @var TrackingRequest $trackingRequest */
        $trackingRequest = TrackingRequest::create([
            'organization_id' => $data['organization_id'],
            'air_shipment_id' => $airShipment->id,
            'carrier_scac'    => $data['carrier_scac'] ?? null,
            'reference_type'  => 'awb',
            'reference_value' => $data['awb_number'],
            'status'          => 'pending',
            'last_polled_at'  => null,
        ]);

        event(new TrackingRequestCreated($trackingRequest));

        return $trackingRequest;
    }

    public function pollAirUpdates(AirShipment $shipment): void
    {
        $trackingRequests = $shipment->trackingRequests()
            ->where('status', '!=', 'failed')
            ->get();

        foreach ($trackingRequests as $request) {
            try {
                $updates = $this->fetchAirCarrierUpdates($request);

                if (!empty($updates)) {
                    $this->applyAirUpdates($shipment, $updates);
                    $request->update(['status' => 'active', 'last_polled_at' => now()]);
                }
            } catch (\Throwable $e) {
                Log::error('AirTrackingService: pollAirUpdates failed', [
                    'tracking_request_id' => $request->id,
                    'error'               => $e->getMessage(),
                ]);
                $request->update(['status' => 'failed']);
            }
        }
    }

    private function fetchAirCarrierUpdates(TrackingRequest $request): array
    {
        // Stub for air carrier API integration (IATA Cargo, individual airline APIs)
        return [];
    }

    private function applyAirUpdates(AirShipment $shipment, array $updates): void
    {
        $updateData = [];

        if (!empty($updates['eta'])) {
            $updateData['eta'] = $updates['eta'];
        }

        if (!empty($updates['ata'])) {
            $updateData['ata'] = $updates['ata'];
        }

        if (!empty($updates['status'])) {
            $updateData['status'] = $updates['status'];
        }

        if (!empty($updateData)) {
            $shipment->update($updateData);
        }
    }
}
