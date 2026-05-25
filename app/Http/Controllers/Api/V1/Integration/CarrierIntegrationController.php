<?php

namespace App\Http\Controllers\Api\V1\Integration;

use App\Http\Controllers\Controller;
use App\Models\Tenant\CarrierApiCredential;
use App\Services\Carrier\CarrierApiManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class CarrierIntegrationController extends Controller
{
    public function __construct(
        private readonly CarrierApiManager $manager,
    ) {}

    /**
     * GET /api/v1/carrier-integrations
     * List all available carriers with connection status.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->manager->getCarrierStatuses(),
        ]);
    }

    /**
     * POST /api/v1/carrier-integrations
     * Store or update carrier API credentials.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'carrier_scac'  => 'required|string|in:MSCU,MAEU,CMDU,COSU,HLCU,ONEY,EGLV,HDMU,YMLU,ZIMU,WHLC,PILU,SUDU,KMTU,XPRU',
            'auth_type'     => 'required|in:api_key,oauth2,consumer_key',
            'api_key'       => 'nullable|string|required_if:auth_type,api_key',
            'consumer_key'  => 'nullable|string|required_if:auth_type,consumer_key',
            'client_id'     => 'nullable|string|required_if:auth_type,oauth2',
            'client_secret' => 'nullable|string|required_if:auth_type,oauth2',
            'environment'   => 'nullable|in:sandbox,production',
            'api_url'       => 'nullable|url',
        ]);

        // Derive carrier name from registry
        $carrierInfo = \App\Services\Tracking\Carriers\CarrierRegistry::getCarrier($validated['carrier_scac']);
        $carrierName = $carrierInfo['name'] ?? $validated['carrier_scac'];

        $credential = CarrierApiCredential::updateOrCreate(
            ['carrier_scac' => $validated['carrier_scac']],
            [
                'carrier_name'  => $carrierName,
                'auth_type'     => $validated['auth_type'],
                'api_key'       => $validated['api_key'] ?? null,
                'consumer_key'  => $validated['consumer_key'] ?? null,
                'client_id'     => $validated['client_id'] ?? null,
                'client_secret' => $validated['client_secret'] ?? null,
                'environment'   => $validated['environment'] ?? 'production',
                'api_url'       => $validated['api_url'] ?? null,
                'is_active'     => true,
                'last_error'    => null,
            ]
        );

        return response()->json([
            'message'    => 'Carrier credentials saved successfully.',
            'credential' => $this->formatCredential($credential),
        ], 201);
    }

    /**
     * GET /api/v1/carrier-integrations/{scac}
     * Show credential details for a specific carrier.
     */
    public function show(string $scac): JsonResponse
    {
        $credential = CarrierApiCredential::where('carrier_scac', strtoupper($scac))->first();

        if (!$credential) {
            return response()->json(['message' => 'No credentials configured for this carrier.'], 404);
        }

        return response()->json(['credential' => $this->formatCredential($credential)]);
    }

    /**
     * DELETE /api/v1/carrier-integrations/{scac}
     * Remove carrier credentials (disconnect).
     */
    public function destroy(string $scac): JsonResponse
    {
        $deleted = CarrierApiCredential::where('carrier_scac', strtoupper($scac))->delete();

        if (!$deleted) {
            return response()->json(['message' => 'No credentials found.'], 404);
        }

        return response()->json(['message' => 'Carrier disconnected successfully.']);
    }

    /**
     * POST /api/v1/carrier-integrations/{scac}/test
     * Test carrier connection by making a simple API call.
     */
    public function test(string $scac): JsonResponse
    {
        try {
            $tracker = $this->manager->tracker(strtoupper($scac));

            if (!$tracker->supportsTracking()) {
                return response()->json([
                    'data' => ['success' => false, 'message' => 'Carrier does not support tracking.'],
                ], 400);
            }

            $tracker->trackByContainer('TEST0000000');

            return response()->json([
                'data' => ['success' => true, 'message' => 'Connection successful. API credentials are valid.'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'data' => ['success' => false, 'message' => 'Connection failed: ' . $e->getMessage()],
            ], 400);
        }
    }

    /**
     * POST /api/v1/carrier-integrations/{scac}/toggle
     * Enable or disable a carrier integration.
     */
    public function toggle(string $scac): JsonResponse
    {
        $credential = CarrierApiCredential::where('carrier_scac', strtoupper($scac))->first();

        if (!$credential) {
            return response()->json(['message' => 'No credentials found.'], 404);
        }

        $credential->update(['is_active' => !$credential->is_active]);

        return response()->json([
            'message'   => $credential->is_active ? 'Carrier enabled.' : 'Carrier disabled.',
            'is_active' => $credential->is_active,
        ]);
    }

    private function formatCredential(CarrierApiCredential $credential): array
    {
        return [
            'id'               => $credential->id,
            'carrier_scac'     => $credential->carrier_scac,
            'carrier_name'     => $credential->carrier_name,
            'auth_type'        => $credential->auth_type,
            'environment'      => $credential->environment,
            'is_active'        => $credential->is_active,
            'last_used_at'     => $credential->last_used_at?->toIso8601String(),
            'has_api_key'      => !empty($credential->api_key),
            'has_consumer_key' => !empty($credential->consumer_key),
            'has_client_id'    => !empty($credential->client_id),
            'created_at'       => $credential->created_at->toIso8601String(),
            'updated_at'       => $credential->updated_at->toIso8601String(),
        ];
    }
}
