<?php

namespace App\Http\Controllers\Api\V1\JsonCargo;

use App\Http\Controllers\Controller;
use App\Services\JsonCargo\JsonCargoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JsonCargoController extends Controller
{
    public function __construct(
        private readonly JsonCargoService $jsonCargo,
    ) {}

    // ================================================================
    // Configuration & Meta
    // ================================================================

    /**
     * Check if JSONCargo integration is configured and list supported lines.
     */
    public function status(): JsonResponse
    {
        return $this->success([
            'configured'       => $this->jsonCargo->isConfigured(),
            'shipping_lines'   => $this->jsonCargo->supportedShippingLines(),
            'scac_mapping'     => JsonCargoService::SCAC_TO_SHIPPING_LINE,
        ]);
    }

    /**
     * Fetch API key usage stats (plan, requests made/available).
     */
    public function apiKeyStats(): JsonResponse
    {
        $stats = $this->jsonCargo->getApiKeyStats();

        if (! $stats) {
            return $this->error('Failed to fetch API key stats. Is JSONCARGO_API_KEY set?', 503);
        }

        if (isset($stats['error'])) {
            return $this->error($stats['error'], $stats['status'] ?? 500);
        }

        return $this->success($stats);
    }

    // ================================================================
    // Container Tracking
    // ================================================================

    /**
     * Get tracking details for a single container.
     *
     * GET /jsoncargo/containers/{tracking_number}?shipping_line=ZIM
     */
    public function containerDetails(Request $request, string $trackingNumber): JsonResponse
    {
        $shippingLine = $request->query('shipping_line');

        $data = $this->jsonCargo->getContainerDetails($trackingNumber, $shippingLine);

        if (! $data) {
            return $this->notFound("Container {$trackingNumber} not found or JSONCargo unavailable.");
        }

        if (isset($data['error'])) {
            return $this->error($data['error'], $data['status'] ?? 500);
        }

        return $this->success($data);
    }

    /**
     * Track multiple containers in one request.
     *
     * POST /jsoncargo/containers/batch
     * Body: { "containers": [{"number": "ZCSU7244544", "shipping_line": "ZIM"}, ...] }
     */
    public function containerBatch(Request $request): JsonResponse
    {
        $request->validate([
            'containers'                 => 'required|array|min:1|max:50',
            'containers.*.number'        => 'required|string',
            'containers.*.shipping_line' => 'nullable|string',
        ]);

        $results = $this->jsonCargo->getContainerDetailsBatch($request->input('containers'));

        // Separate successes and failures
        $tracked = [];
        $failed  = [];

        foreach ($results as $number => $data) {
            if (! $data || isset($data['error'])) {
                $failed[$number] = $data['error'] ?? 'Not found';
            } else {
                $tracked[$number] = $data;
            }
        }

        return $this->success([
            'tracked'       => $tracked,
            'failed'        => $failed,
            'total_tracked' => count($tracked),
            'total_failed'  => count($failed),
        ]);
    }

    /**
     * Get containers associated with a Bill of Lading.
     *
     * GET /jsoncargo/containers/bol/{bol_number}?shipping_line=ZIM
     */
    public function containersByBol(Request $request, string $bolNumber): JsonResponse
    {
        $request->validate([
            'shipping_line' => 'required|string',
        ]);

        $data = $this->jsonCargo->getContainersByBol($bolNumber, $request->query('shipping_line'));

        if (! $data) {
            return $this->notFound("No containers found for BOL {$bolNumber}.");
        }

        if (isset($data['error'])) {
            return $this->error($data['error'], $data['status'] ?? 500);
        }

        return $this->success($data);
    }

    // ================================================================
    // Vessel Tracking
    // ================================================================

    /**
     * Get basic live vessel tracking.
     *
     * GET /jsoncargo/vessels/basic?imo=9525338
     */
    public function vesselBasic(Request $request): JsonResponse
    {
        $params = $request->only(['uuid', 'mmsi', 'imo', 'page', 'limit']);

        if (! array_intersect_key($params, array_flip(['uuid', 'mmsi', 'imo']))) {
            return $this->error('Provide at least one of: uuid, mmsi, imo', 422);
        }

        $data = $this->jsonCargo->getVesselBasic($params);

        if (! $data) {
            return $this->notFound('Vessel not found.');
        }

        if (isset($data['error'])) {
            return $this->error($data['error'], $data['status'] ?? 500);
        }

        return $this->success($data);
    }

    /**
     * Get pro vessel tracking with ports and ATD/ETA.
     *
     * GET /jsoncargo/vessels/pro?imo=9525338
     */
    public function vesselPro(Request $request): JsonResponse
    {
        $params = $request->only(['uuid', 'mmsi', 'imo', 'page', 'limit']);

        if (! array_intersect_key($params, array_flip(['uuid', 'mmsi', 'imo']))) {
            return $this->error('Provide at least one of: uuid, mmsi, imo', 422);
        }

        $data = $this->jsonCargo->getVesselPro($params);

        if (! $data) {
            return $this->notFound('Vessel not found.');
        }

        if (isset($data['error'])) {
            return $this->error($data['error'], $data['status'] ?? 500);
        }

        return $this->success($data);
    }

    /**
     * Bulk track up to 100 vessels.
     *
     * GET /jsoncargo/vessels/bulk?imo=9525338,9286592
     */
    public function vesselBulk(Request $request): JsonResponse
    {
        $type = $request->query('type', 'imo');

        if (! in_array($type, ['uuid', 'mmsi', 'imo'])) {
            return $this->error('Type must be one of: uuid, mmsi, imo', 422);
        }

        $ids = $request->query('ids');

        if (! $ids) {
            return $this->error('Provide comma-separated identifiers in the "ids" parameter.', 422);
        }

        $identifiers = array_map('trim', explode(',', $ids));

        if (count($identifiers) > 100) {
            return $this->error('Maximum 100 vessels per request.', 422);
        }

        $data = $this->jsonCargo->getVesselBulk($identifiers, $type);

        if (! $data) {
            return $this->notFound('No vessels found.');
        }

        if (isset($data['error'])) {
            return $this->error($data['error'], $data['status'] ?? 500);
        }

        return $this->success($data);
    }

    /**
     * Search for vessels by name, type, specs, etc.
     *
     * GET /jsoncargo/vessels/find?name=MSC+RIKKU&type_specific=Container+Ship
     */
    public function vesselFinder(Request $request): JsonResponse
    {
        $params = $request->only([
            'name', 'fuzzy', 'type', 'type_specific', 'country_iso',
            'gross_tonnage_min', 'gross_tonnage_max',
            'deadweight_min', 'deadweight_max',
            'length_min', 'length_max',
            'breadth_min', 'breadth_max',
            'year_built_min', 'year_built_max',
            'next', 'page', 'limit',
        ]);

        if (empty($params)) {
            return $this->error('Provide at least one search parameter.', 422);
        }

        $data = $this->jsonCargo->findVessels($params);

        if (! $data) {
            return $this->notFound('No vessels found.');
        }

        if (isset($data['error'])) {
            return $this->error($data['error'], $data['status'] ?? 500);
        }

        return $this->success($data);
    }

    /**
     * Get vessel specs by UUID, MMSI, or IMO.
     *
     * GET /jsoncargo/vessels/specs?imo=9525338
     */
    public function vesselSpecs(Request $request): JsonResponse
    {
        $params = $request->only(['uuid', 'mmsi', 'imo', 'page', 'limit']);

        if (! array_intersect_key($params, array_flip(['uuid', 'mmsi', 'imo']))) {
            return $this->error('Provide at least one of: uuid, mmsi, imo', 422);
        }

        $data = $this->jsonCargo->getVesselSpecs($params);

        if (! $data) {
            return $this->notFound('Vessel specs not found.');
        }

        if (isset($data['error'])) {
            return $this->error($data['error'], $data['status'] ?? 500);
        }

        return $this->success($data);
    }

    // ================================================================
    // Port & Terminal
    // ================================================================

    /**
     * Search for ports by name, coordinates, country.
     *
     * GET /jsoncargo/ports/find?name=Everglades&country_iso=US
     */
    public function portFinder(Request $request): JsonResponse
    {
        $params = $request->only([
            'lat', 'lon', 'radius', 'name', 'country_iso',
            'port_type', 'fuzzy', 'page', 'limit',
        ]);

        if (empty($params)) {
            return $this->error('Provide at least one search parameter.', 422);
        }

        $data = $this->jsonCargo->findPorts($params);

        if (! $data) {
            return $this->notFound('No ports found.');
        }

        if (isset($data['error'])) {
            return $this->error($data['error'], $data['status'] ?? 500);
        }

        return $this->success($data);
    }

    /**
     * Find terminals by UN/LOCODE.
     *
     * GET /jsoncargo/terminals/find?unlocode=USEVG
     */
    public function terminalFinder(Request $request): JsonResponse
    {
        $request->validate([
            'unlocode' => 'required|string|min:2',
        ]);

        $data = $this->jsonCargo->findTerminals($request->query('unlocode'));

        if (! $data) {
            return $this->notFound('No terminals found for this UNLOCODE.');
        }

        if (isset($data['error'])) {
            return $this->error($data['error'], $data['status'] ?? 500);
        }

        return $this->success($data);
    }

    // ================================================================
    // Cache Management
    // ================================================================

    /**
     * Refresh tracking data for a container (clears cache and re-fetches).
     *
     * POST /jsoncargo/containers/{tracking_number}/refresh?shipping_line=ZIM
     */
    public function refreshContainer(Request $request, string $trackingNumber): JsonResponse
    {
        $this->jsonCargo->flushContainerCache($trackingNumber);

        $shippingLine = $request->query('shipping_line');
        $data = $this->jsonCargo->getContainerDetails($trackingNumber, $shippingLine);

        if (! $data) {
            return $this->notFound("Container {$trackingNumber} not found after refresh.");
        }

        if (isset($data['error'])) {
            return $this->error($data['error'], $data['status'] ?? 500);
        }

        return $this->success($data, 'Container data refreshed.');
    }
}
