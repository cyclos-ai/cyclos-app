<?php

namespace App\Http\Controllers\Api\V1\Vessel;

use App\Http\Controllers\Controller;
use App\Services\Vessel\DatalasticService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AisController extends Controller
{
    public function __construct(
        private readonly DatalasticService $datalastic,
    ) {}

    // ================================================================
    // Configuration & Meta
    // ================================================================

    /**
     * Check if the Datalastic AIS integration is configured.
     *
     * GET /api/v1/ais/status
     */
    public function status(): JsonResponse
    {
        return $this->success([
            'configured' => $this->datalastic->isConfigured(),
        ]);
    }

    // ================================================================
    // Vessel Map
    // ================================================================

    /**
     * Return all vessels inside a viewport circle (lat/lon + radius).
     *
     * GET /api/v1/ais/vessels?lat=&lon=&radius=
     *
     * If the API key is not configured, returns a 200 with configured:false
     * so the frontend map can display a friendly "not configured" message
     * instead of treating it as an error.
     */
    public function vessels(Request $request): JsonResponse
    {
        if (! $this->datalastic->isConfigured()) {
            return $this->success([
                'configured' => false,
                'data'       => ['vessels' => [], 'count' => 0],
                'message'    => 'Datalastic API key not set',
            ]);
        }

        $request->validate([
            'lat'    => 'required|numeric|between:-90,90',
            'lon'    => 'required|numeric|between:-180,180',
            'radius' => 'nullable|numeric|min:1|max:100',
        ]);

        $lat    = (float) $request->input('lat');
        $lon    = (float) $request->input('lon');
        $radius = (float) $request->input('radius', 100);

        $result = $this->datalastic->vesselsInRadius($lat, $lon, $radius);

        if (isset($result['error'])) {
            return $this->error($result['error'], 502);
        }

        // Record billable external AIS lookup (guarded — never breaks the request).
        try {
            app(\App\Services\Billing\UsageMeteringService::class)
                ->recordApiCall(tenancy()->tenant?->id, 'ais', true);
        } catch (\Throwable $e) {
            // ignore metering failures
        }

        return $this->success([
            'configured' => true,
            'data'       => [
                'vessels' => $result['vessels'],
                'count'   => $result['count'],
            ],
        ]);
    }

    // ================================================================
    // Vessel Detail
    // ================================================================

    /**
     * Proxy vessel detail (pro) from Datalastic.
     *
     * GET /api/v1/ais/vessel?imo=&mmsi=
     */
    public function vessel(Request $request): JsonResponse
    {
        $params = $request->only(['imo', 'mmsi', 'uuid']);

        if (! array_intersect_key($params, array_flip(['imo', 'mmsi', 'uuid']))) {
            return $this->error('Provide at least one of: imo, mmsi, uuid', 422);
        }

        if (! $this->datalastic->isConfigured()) {
            return $this->success([
                'configured' => false,
                'data'       => null,
                'message'    => 'Datalastic API key not set',
            ]);
        }

        $data = $this->datalastic->vesselDetail($params);

        if ($data === null) {
            return $this->notFound('Vessel not found or Datalastic unavailable.');
        }

        // Record billable external AIS lookup (guarded — never breaks the request).
        try {
            app(\App\Services\Billing\UsageMeteringService::class)
                ->recordApiCall(tenancy()->tenant?->id, 'ais', true);
        } catch (\Throwable $e) {
            // ignore metering failures
        }

        return $this->success([
            'configured' => true,
            'data'       => $data,
        ]);
    }
}
