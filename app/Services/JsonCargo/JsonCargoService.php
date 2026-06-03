<?php

namespace App\Services\JsonCargo;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

class JsonCargoService
{
    private ?string $apiKey;
    private string $baseUrl;
    private int $cacheTtl;

    /**
     * SCAC → JSONCargo shipping_line query param mapping.
     */
    public const SCAC_TO_SHIPPING_LINE = [
        'MAEU' => 'MAERSK',
        'MAEI' => 'MAERSK',
        'MRKU' => 'MAERSK',
        'MSKU' => 'MAERSK',
        'HLCU' => 'HAPAG_LLOYD',
        'HLXU' => 'HAPAG_LLOYD',
        'HDMU' => 'HMM',
        'ONEY' => 'ONE',
        'EGLV' => 'EVERGREEN',
        'MSCU' => 'MSC',
        'MEDU' => 'MSC',
        'CMDU' => 'CMA_CGM',
        'CMAU' => 'CMA_CGM',
        'COSU' => 'COSCO',
        'CCLU' => 'COSCO',
        'ZIMU' => 'ZIM',
        'ZCSU' => 'ZIM',       // ZIM-leased third-party prefix
        'ZLCU' => 'ZIM',       // ZIM-leased third-party prefix
        'JXLU' => 'ZIM',       // ZIM-leased third-party prefix
        'TLLU' => 'ZIM',       // ZIM-leased third-party prefix
        'CAAU' => 'ZIM',       // ZIM-leased third-party prefix
        'YMLU' => 'YANG_MING',
        'PILU' => 'PIL',
    ];

    /**
     * JSONCargo internal ID → shipping line name mapping.
     */
    public const ID_TO_SHIPPING_LINE = [
        '0010' => 'MAERSK',
        '0011' => 'HAPAG_LLOYD',
        '0012' => 'HMM',
        '0013' => 'ONE',
        '0014' => 'EVERGREEN',
        '0015' => 'MSC',
        '0016' => 'CMA_CGM',
        '0017' => 'COSCO',
        '0018' => 'ZIM',
        '0019' => 'YANG_MING',
        '0020' => 'PIL',
    ];

    private CacheRepository $cache;

    public function __construct()
    {
        $this->apiKey   = config('services.jsoncargo.api_key');
        $this->baseUrl  = rtrim(config('services.jsoncargo.base_url', 'http://api.jsoncargo.com/api/v1'), '/');
        $this->cacheTtl = (int) config('services.jsoncargo.cache_ttl', 900);
        // Use the file store directly to bypass tenancy's tagged cache wrapper
        $this->cache    = Cache::store('file');
    }

    /**
     * Check if the service is configured with an API key.
     */
    public function isConfigured(): bool
    {
        return ! empty($this->apiKey);
    }

    /**
     * Resolve a SCAC code to a JSONCargo shipping line name.
     */
    public function resolveShippingLine(string $scac): ?string
    {
        return self::SCAC_TO_SHIPPING_LINE[strtoupper($scac)] ?? null;
    }

    /**
     * Detect the shipping line from a container prefix (first 4 chars).
     */
    public function detectShippingLineFromContainer(string $containerNumber): ?string
    {
        $prefix = strtoupper(substr($containerNumber, 0, 4));

        return self::SCAC_TO_SHIPPING_LINE[$prefix] ?? null;
    }

    /**
     * Get the list of supported shipping lines.
     */
    public function supportedShippingLines(): array
    {
        return [
            ['name' => 'A.P. Moller - Maersk',                    'code' => 'MAERSK',      'id' => '0010'],
            ['name' => 'Hapag-Lloyd',                              'code' => 'HAPAG_LLOYD', 'id' => '0011'],
            ['name' => 'Hyundai Merchant Marine (HMM)',            'code' => 'HMM',         'id' => '0012'],
            ['name' => 'Ocean Network Express (ONE)',              'code' => 'ONE',         'id' => '0013'],
            ['name' => 'Evergreen Marine Corp',                    'code' => 'EVERGREEN',   'id' => '0014'],
            ['name' => 'Mediterranean Shipping Company (MSC)',     'code' => 'MSC',         'id' => '0015'],
            ['name' => 'CMA CGM',                                  'code' => 'CMA_CGM',     'id' => '0016'],
            ['name' => 'COSCO Shipping Lines',                     'code' => 'COSCO',       'id' => '0017'],
            ['name' => 'ZIM Integrated Shipping Services',         'code' => 'ZIM',         'id' => '0018'],
            ['name' => 'Yang Ming Marine Transport',               'code' => 'YANG_MING',   'id' => '0019'],
            ['name' => 'Pacific International Lines (PIL)',        'code' => 'PIL',         'id' => '0020'],
        ];
    }

    // ================================================================
    // Endpoint 1: Get Container Details
    // ================================================================

    /**
     * Fetch container tracking details by tracking number.
     *
     * @param  string      $trackingNumber  Container number (e.g. ZCSU7244544)
     * @param  string|null $shippingLine    Shipping line code (e.g. ZIM). Auto-detected from prefix if null.
     * @return array|null  Container data or null on failure
     */
    public function getContainerDetails(string $trackingNumber, ?string $shippingLine = null): ?array
    {
        $trackingNumber = strtoupper(trim($trackingNumber));

        // Auto-detect shipping line from container prefix if not provided
        if (! $shippingLine) {
            $shippingLine = $this->detectShippingLineFromContainer($trackingNumber);
        }

        $cacheKey = "jsoncargo_container_{$trackingNumber}_{$shippingLine}";

        return $this->cache->remember($cacheKey, $this->cacheTtl, function () use ($trackingNumber, $shippingLine) {
            $query = $shippingLine ? ['shipping_line' => $shippingLine] : [];

            $response = $this->request('GET', "/containers/{$trackingNumber}", $query);

            return $response ? ($response['data'] ?? null) : null;
        });
    }

    /**
     * Track multiple containers in one batch.
     *
     * @param  array  $containers  Array of ['number' => 'XXXX1234567', 'shipping_line' => 'ZIM'] items
     * @return array  Keyed by container number
     */
    public function getContainerDetailsBatch(array $containers): array
    {
        $results = [];

        foreach ($containers as $container) {
            $number = $container['number'] ?? $container;
            $line   = $container['shipping_line'] ?? null;

            $results[$number] = $this->getContainerDetails($number, $line);
        }

        return $results;
    }

    // ================================================================
    // Endpoint 2: Get Container Numbers From Bill of Lading
    // ================================================================

    /**
     * Fetch container numbers associated with a Bill of Lading.
     *
     * @param  string $bolNumber     Bill of Lading number
     * @param  string $shippingLine  Required shipping line code (e.g. ZIM)
     * @return array|null
     */
    public function getContainersByBol(string $bolNumber, string $shippingLine): ?array
    {
        $bolNumber    = strtoupper(trim($bolNumber));
        $shippingLine = strtoupper(trim($shippingLine));

        $cacheKey = "jsoncargo_bol_{$bolNumber}_{$shippingLine}";

        return $this->cache->remember($cacheKey, $this->cacheTtl, function () use ($bolNumber, $shippingLine) {
            $response = $this->request('GET', "/containers/bol/{$bolNumber}", [
                'shipping_line' => $shippingLine,
            ]);

            return $response ? ($response['data'] ?? null) : null;
        });
    }

    // ================================================================
    // Endpoint 3: Get Basic Live Vessel Tracking Details
    // ================================================================

    /**
     * Fetch basic live vessel tracking by UUID, MMSI, or IMO.
     *
     * @param  array $params  ['uuid' => '...'] or ['mmsi' => '...'] or ['imo' => '...']
     * @return array|null
     */
    public function getVesselBasic(array $params): ?array
    {
        $cacheKey = 'jsoncargo_vessel_basic_' . md5(json_encode($params));

        return $this->cache->remember($cacheKey, $this->cacheTtl, function () use ($params) {
            $response = $this->request('GET', '/vessel/basic', $params);

            return $response ? ($response['data'] ?? null) : null;
        });
    }

    // ================================================================
    // Endpoint 4: Get Pro Live Vessel Tracking Details
    // ================================================================

    /**
     * Fetch pro vessel tracking with departure/arrival ports, ATD, ETA.
     *
     * @param  array $params  ['uuid' => '...'] or ['mmsi' => '...'] or ['imo' => '...']
     * @return array|null
     */
    public function getVesselPro(array $params): ?array
    {
        $cacheKey = 'jsoncargo_vessel_pro_' . md5(json_encode($params));

        return $this->cache->remember($cacheKey, $this->cacheTtl, function () use ($params) {
            $response = $this->request('GET', '/vessel/pro', $params);

            return $response ? ($response['data'] ?? null) : null;
        });
    }

    // ================================================================
    // Endpoint 5: Get Bulk Live Vessel Tracking Details
    // ================================================================

    /**
     * Fetch up to 100 vessels at once by UUID, MMSI, or IMO.
     *
     * @param  array $identifiers  Comma-separated UUIDs, MMSIs, or IMOs
     * @param  string $type        'uuid', 'mmsi', or 'imo'
     * @return array|null
     */
    public function getVesselBulk(array $identifiers, string $type = 'uuid'): ?array
    {
        $cacheKey = 'jsoncargo_vessel_bulk_' . md5($type . implode(',', $identifiers));

        return $this->cache->remember($cacheKey, $this->cacheTtl, function () use ($identifiers, $type) {
            $response = $this->request('GET', '/vessel/bulk', [
                $type => implode(',', $identifiers),
            ]);

            return $response ? ($response['data'] ?? null) : null;
        });
    }

    // ================================================================
    // Endpoint 6: Vessel Finder API
    // ================================================================

    /**
     * Search for vessels by name, type, specs, etc.
     *
     * @param  array $params  Search filters (name, fuzzy, type, type_specific, country_iso,
     *                        gross_tonnage_min/max, deadweight_min/max, length_min/max,
     *                        breadth_min/max, year_built_min/max, next, page, limit)
     * @return array|null
     */
    public function findVessels(array $params): ?array
    {
        $cacheKey = 'jsoncargo_vessel_finder_' . md5(json_encode($params));

        return $this->cache->remember($cacheKey, $this->cacheTtl, function () use ($params) {
            $response = $this->request('GET', '/vessel/finder', $params);

            return $response ? ($response['data'] ?? null) : null;
        });
    }

    // ================================================================
    // Endpoint 7: Vessel Specs Details API
    // ================================================================

    /**
     * Fetch vessel specifications by UUID, MMSI, or IMO.
     *
     * @param  array $params  ['uuid' => '...'] or ['mmsi' => '...'] or ['imo' => '...']
     * @return array|null
     */
    public function getVesselSpecs(array $params): ?array
    {
        $cacheKey = 'jsoncargo_vessel_specs_' . md5(json_encode($params));

        return $this->cache->remember($cacheKey, $this->cacheTtl, function () use ($params) {
            $response = $this->request('GET', '/vessel/specs', $params);

            return $response ? ($response['data'] ?? null) : null;
        });
    }

    // ================================================================
    // Endpoint 8: Port Finder
    // ================================================================

    /**
     * Search for ports by name, coordinates, country, or type.
     *
     * @param  array $params  (lat, lon, radius, name, country_iso, port_type, fuzzy, page, limit)
     * @return array|null
     */
    public function findPorts(array $params): ?array
    {
        $cacheKey = 'jsoncargo_port_finder_' . md5(json_encode($params));

        return $this->cache->remember($cacheKey, $this->cacheTtl * 4, function () use ($params) {
            $response = $this->request('GET', '/port/find', $params);

            return $response ? ($response['data'] ?? null) : null;
        });
    }

    // ================================================================
    // Endpoint 9: Terminal Finder API
    // ================================================================

    /**
     * Find terminals by UN/LOCODE.
     *
     * @param  string $unlocode  UN/LOCODE (minimum 2 chars, e.g. "USEVG" for Port Everglades)
     * @return array|null
     */
    public function findTerminals(string $unlocode): ?array
    {
        $unlocode = strtoupper(trim($unlocode));

        $cacheKey = "jsoncargo_terminal_{$unlocode}";

        return $this->cache->remember($cacheKey, $this->cacheTtl * 4, function () use ($unlocode) {
            $response = $this->request('GET', '/terminal', [
                'unlocode' => $unlocode,
            ]);

            return $response ? ($response['data'] ?? null) : null;
        });
    }

    // ================================================================
    // Endpoint 10: Get API Key Usage Stats
    // ================================================================

    /**
     * Fetch API key usage statistics (plan, requests made/available).
     *
     * @return array|null
     */
    public function getApiKeyStats(): ?array
    {
        // No caching — always fetch fresh stats
        $response = $this->request('GET', '/api_key/stats');

        return $response ? ($response['data'] ?? null) : null;
    }

    // ================================================================
    // Internal HTTP Client
    // ================================================================

    /**
     * Make an authenticated request to the JSONCargo API.
     *
     * @param  string $method  HTTP method
     * @param  string $path    API path (e.g. /containers/XXXX)
     * @param  array  $query   Query parameters
     * @return array|null      Decoded JSON response or null on failure
     */
    private function request(string $method, string $path, array $query = []): ?array
    {
        if (! $this->isConfigured()) {
            Log::warning('JSONCargo: API key not configured. Set JSONCARGO_API_KEY in .env');

            return null;
        }

        try {
            $response = Http::timeout(8)
                ->connectTimeout(5)
                ->retry(1, 250)
                ->withHeaders([
                    'x-api-key' => $this->apiKey,
                    'Accept'    => 'application/json',
                ])
                ->$method($this->baseUrl . $path, $query);

            if ($response->successful()) {
                return $response->json();
            }

            $status = $response->status();
            $body   = $response->json() ?? [];

            Log::warning("JSONCargo: {$method} {$path} returned {$status}", [
                'query'    => $query,
                'response' => $body,
            ]);

            // Return error structure so controller can relay meaningful messages
            if ($status === 404) {
                return ['error' => $body['title'] ?? 'Not found', 'status' => 404];
            }

            if ($status === 429) {
                return ['error' => 'Rate limit exceeded. Check your JSONCargo plan.', 'status' => 429];
            }

            return ['error' => $body['title'] ?? 'JSONCargo API error', 'status' => $status];
        } catch (\Throwable $e) {
            Log::error('JSONCargo: Request failed', [
                'path'    => $path,
                'query'   => $query,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Flush all JSONCargo cache entries for a specific container.
     */
    public function flushContainerCache(string $trackingNumber): void
    {
        $trackingNumber = strtoupper(trim($trackingNumber));

        foreach (array_values(self::SCAC_TO_SHIPPING_LINE) as $line) {
            $this->cache->forget("jsoncargo_container_{$trackingNumber}_{$line}");
        }

        $this->cache->forget("jsoncargo_container_{$trackingNumber}_");
    }
}
