<?php

namespace App\Services\Tracking;

use App\Events\Vessel\VesselETAUpdated;
use App\Events\Vessel\VesselPositionUpdated;
use App\Models\Tenant\Vessel;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class VesselTrackingService
{
    // Average ocean freight speed in knots (approximately 14 knots = ~26 km/h)
    private const AVG_SPEED_KNOTS = 14.0;

    public function pollAISPositions(): void
    {
        // Stub: In production this fetches AIS data from an external provider
        // (e.g., MarineTraffic, VesselFinder, SpireAIS)
        $vessels = Vessel::whereNotNull('imo_number')
            ->whereNull('ata')
            ->get();

        foreach ($vessels as $vessel) {
            $aisData = $this->fetchAISData($vessel->imo_number);

            if (!empty($aisData)) {
                $this->updateVesselPosition(
                    $vessel,
                    (float) $aisData['latitude'],
                    (float) $aisData['longitude'],
                    $aisData
                );
            }
        }
    }

    public function updateVesselPosition(Vessel $vessel, float $lat, float $lng, array $data = []): void
    {
        $previousEta = $vessel->eta;

        $vessel->update([
            'current_latitude'  => $lat,
            'current_longitude' => $lng,
            'current_speed'     => $data['speed'] ?? $vessel->current_speed,
            'current_heading'   => $data['heading'] ?? $vessel->current_heading,
            'last_ais_update'   => now(),
        ]);

        event(new VesselPositionUpdated($vessel, $lat, $lng));

        $newEta = $this->recalculateETA($vessel);

        if ($newEta !== null && ($previousEta === null || !$newEta->equalTo($previousEta))) {
            event(new VesselETAUpdated($vessel, $previousEta, $newEta));
        }
    }

    public function recalculateETA(Vessel $vessel): ?Carbon
    {
        if ($vessel->current_latitude === null || $vessel->current_longitude === null) {
            return null;
        }

        if ($vessel->destination_latitude === null || $vessel->destination_longitude === null) {
            return null;
        }

        $distanceNm = $this->haversineDistanceNm(
            (float) $vessel->current_latitude,
            (float) $vessel->current_longitude,
            (float) $vessel->destination_latitude,
            (float) $vessel->destination_longitude
        );

        $speedKnots = (float) ($vessel->current_speed > 0 ? $vessel->current_speed : self::AVG_SPEED_KNOTS);
        $hoursRemaining = $distanceNm / $speedKnots;

        $newEta = now()->addHours((int) ceil($hoursRemaining));

        $vessel->update(['eta' => $newEta]);

        return $newEta;
    }

    public function getVesselRoute(Vessel $vessel): Collection
    {
        // Returns position history; in production this would query a vessel_positions table
        return collect([
            [
                'latitude'   => $vessel->current_latitude,
                'longitude'  => $vessel->current_longitude,
                'speed'      => $vessel->current_speed,
                'heading'    => $vessel->current_heading,
                'recorded_at' => $vessel->last_ais_update,
            ],
        ]);
    }

    private function fetchAISData(string $imoNumber): array
    {
        // Stub for AIS provider integration
        return [];
    }

    private function haversineDistanceNm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadiusNm = 3440.065; // nautical miles

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusNm * $c;
    }
}
