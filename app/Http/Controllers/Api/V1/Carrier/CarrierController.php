<?php

namespace App\Http\Controllers\Api\V1\Carrier;

use App\Http\Controllers\Controller;
use App\Services\Tracking\Carriers\CarrierRegistry;
use App\Services\Tracking\Carriers\CarrierTrackerFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CarrierController extends Controller
{
    public function __construct(
        private readonly CarrierTrackerFactory $trackerFactory,
    ) {}

    /**
     * GET /api/v1/carriers
     * List all known steamship lines.
     */
    public function index(): JsonResponse
    {
        $carriers = [];

        foreach (CarrierRegistry::getAllCarriers() as $scac => $carrier) {
            $carriers[] = $this->formatCarrier($scac, $carrier);
        }

        return $this->success($carriers);
    }

    /**
     * GET /api/v1/carriers/supported
     * List carriers with active API tracking support.
     */
    public function supported(): JsonResponse
    {
        $carriers = [];

        foreach (CarrierRegistry::getSupportedCarriers() as $scac => $carrier) {
            $entry                    = $this->formatCarrier($scac, $carrier);
            $entry['has_dedicated_integration'] = $this->trackerFactory->supports($scac);
            $carriers[]               = $entry;
        }

        return $this->success($carriers);
    }

    /**
     * GET /api/v1/carriers/groups
     * List carrier alliance/corporate groups and their member SCACs.
     */
    public function groups(): JsonResponse
    {
        $groups = [];

        foreach (CarrierRegistry::getCarrierGroups() as $groupName => $scacs) {
            $members = [];
            foreach ($scacs as $scac) {
                $carrier   = CarrierRegistry::getCarrier($scac);
                $members[] = [
                    'scac' => $scac,
                    'name' => $carrier['name'] ?? $scac,
                ];
            }

            $groups[] = [
                'group'   => $groupName,
                'members' => $members,
            ];
        }

        return $this->success($groups);
    }

    /**
     * GET /api/v1/carriers/search?q={query}
     * Search carriers by name, SCAC, or alias.
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->input('q', '');

        if (empty(trim($query))) {
            return $this->error('Query parameter "q" is required.', 422);
        }

        $results  = [];
        foreach (CarrierRegistry::searchCarriers($query) as $scac => $carrier) {
            $results[] = $this->formatCarrier($scac, $carrier);
        }

        return $this->success($results);
    }

    /**
     * GET /api/v1/carriers/{scac}
     * Get details for a specific carrier by SCAC (aliases are resolved).
     */
    public function show(string $scac): JsonResponse
    {
        $resolvedScac = CarrierRegistry::resolveScac(strtoupper($scac));
        $carrier      = CarrierRegistry::getCarrier($resolvedScac);

        if ($carrier === null) {
            return $this->notFound("Carrier '{$scac}' not found.");
        }

        $data                              = $this->formatCarrier($resolvedScac, $carrier);
        $data['has_dedicated_integration'] = $this->trackerFactory->supports($resolvedScac);

        return $this->success($data);
    }

    /**
     * POST /api/v1/carriers/{scac}/track
     * Track a shipment reference with a specific carrier.
     *
     * Body:
     *   reference_type: mbl | container | booking
     *   reference_value: string
     */
    public function track(Request $request, string $scac): JsonResponse
    {
        $request->validate([
            'reference_type'  => 'required|in:mbl,container,booking',
            'reference_value' => 'required|string|max:100',
        ]);

        $resolvedScac = CarrierRegistry::resolveScac(strtoupper($scac));
        $carrier      = CarrierRegistry::getCarrier($resolvedScac);

        if ($carrier === null) {
            return $this->notFound("Carrier '{$scac}' not found.");
        }

        $tracker       = $this->trackerFactory->make($resolvedScac);
        $referenceType = $request->input('reference_type');
        $referenceValue = $request->input('reference_value');

        $response = match ($referenceType) {
            'mbl'       => $tracker->trackByMBL($referenceValue),
            'container' => $tracker->trackByContainer($referenceValue),
            'booking'   => $tracker->trackByBooking($referenceValue),
        };

        if (!$response->success) {
            return $this->error($response->errorMessage ?? 'Tracking request failed.', 502);
        }

        return $this->success($response->toArray());
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

    private function formatCarrier(string $scac, array $carrier): array
    {
        return [
            'scac'          => $scac,
            'name'          => $carrier['name'],
            'group'         => $carrier['group'],
            'api_type'      => $carrier['api_type'],
            'tracking_url'  => $carrier['tracking_url'],
            'website'       => $carrier['website'],
            'aliases'       => $carrier['aliases'],
            'supports_tracking' => $carrier['tracking_url'] !== null,
        ];
    }
}
