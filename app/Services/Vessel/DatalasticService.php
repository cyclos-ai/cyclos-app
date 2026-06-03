<?php

namespace App\Services\Vessel;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DatalasticService
{
    private ?string $apiKey;
    private string $baseUrl;
    private int $cacheTtl;
    private CacheRepository $cache;

    public function __construct()
    {
        $this->apiKey   = config('services.datalastic.api_key');
        $this->baseUrl  = rtrim(config('services.datalastic.base_url', 'https://api.datalastic.com/api/v0'), '/');
        $this->cacheTtl = (int) config('services.datalastic.cache_ttl', 120);
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

    // ================================================================
    // Endpoint 1: Vessels in Radius
    // ================================================================

    /**
     * Fetch all vessels within a given radius of a coordinate.
     *
     * Results are cached keyed by rounded lat/lon/radius to avoid
     * hammering the API on every map pan.
     *
     * @param  float $lat       Latitude (decimal degrees)
     * @param  float $lon       Longitude (decimal degrees)
     * @param  float $radiusNm  Search radius in nautical miles (max 100)
     * @return array  Shape: ['vessels' => [...], 'count' => N] or ['error' => '...', 'vessels' => []]
     */
    public function vesselsInRadius(float $lat, float $lon, float $radiusNm = 100): array
    {
        if (! $this->isConfigured()) {
            Log::warning('Datalastic: API key not configured. Set DATALASTIC_API_KEY in .env');

            return ['vessels' => [], 'count' => 0];
        }

        // Round to 2 decimal places (~1 km) so nearby requests share a cache entry
        $roundedLat    = round($lat, 2);
        $roundedLon    = round($lon, 2);
        $roundedRadius = round($radiusNm, 1);
        $cacheKey      = "datalastic_radius_{$roundedLat}_{$roundedLon}_{$roundedRadius}";

        return $this->cache->remember($cacheKey, $this->cacheTtl, function () use ($lat, $lon, $radiusNm) {
            $response = $this->request('/vessel_inradius', [
                'lat'    => $lat,
                'lon'    => $lon,
                'radius' => $radiusNm,
            ]);

            if ($response === null) {
                return ['vessels' => [], 'count' => 0];
            }

            if (isset($response['error'])) {
                return ['error' => $response['error'], 'vessels' => [], 'count' => 0];
            }

            $raw = $response['data'] ?? [];

            // API returns { data: { vessels: [...] } } or { data: [] } when empty
            $vessels = is_array($raw) && isset($raw['vessels']) ? $raw['vessels'] : [];

            if (! is_array($vessels)) {
                $vessels = [];
            }

            return [
                'vessels' => array_map([$this, 'normalizeVessel'], $vessels),
                'count'   => count($vessels),
            ];
        });
    }

    // ================================================================
    // Endpoint 2: Vessel Detail (Pro)
    // ================================================================

    /**
     * Fetch pro vessel detail including live position, dep/dest ports, ETA.
     *
     * @param  array $params  One of: ['imo' => '...'], ['mmsi' => '...'], ['uuid' => '...']
     * @return array|null     Vessel data or null on failure
     */
    public function vesselDetail(array $params): ?array
    {
        if (! $this->isConfigured()) {
            Log::warning('Datalastic: API key not configured. Set DATALASTIC_API_KEY in .env');

            return null;
        }

        $cacheKey = 'datalastic_vessel_pro_' . md5(json_encode($params));

        return $this->cache->remember($cacheKey, $this->cacheTtl, function () use ($params) {
            $response = $this->request('/vessel_pro', $params);

            if ($response === null || isset($response['error'])) {
                return null;
            }

            return $response['data'] ?? null;
        });
    }

    // ================================================================
    // Internal Helpers
    // ================================================================

    /**
     * Normalize a raw vessel record to a consistent shape.
     *
     * @param  array $raw  Raw vessel object from Datalastic
     * @return array
     */
    private function normalizeVessel(array $raw): array
    {
        return [
            'uuid'                => $raw['uuid']                ?? null,
            'name'                => $raw['name']                ?? null,
            'mmsi'                => $raw['mmsi']                ?? null,
            'imo'                 => $raw['imo']                 ?? null,
            'eni'                 => $raw['eni']                 ?? null,
            'country_iso'         => $raw['country_iso']         ?? null,
            'type'                => $raw['type']                ?? null,
            'type_specific'       => $raw['type_specific']       ?? null,
            'lat'                 => $raw['lat']                 ?? null,
            'lon'                 => $raw['lon']                 ?? null,
            'speed'               => $raw['speed']               ?? null,
            'course'              => $raw['course']              ?? null,
            'heading'             => $raw['heading']             ?? null,
            'destination'         => $raw['destination']         ?? null,
            'last_position_UTC'   => $raw['last_position_UTC']   ?? null,
        ];
    }

    /**
     * Make an authenticated GET request to the Datalastic API.
     *
     * @param  string $path   API path (e.g. /vessel_inradius)
     * @param  array  $query  Query parameters (api-key is appended automatically)
     * @return array|null     Decoded JSON or null on connection failure
     */
    private function request(string $path, array $query = []): ?array
    {
        $query['api-key'] = $this->apiKey;

        try {
            $response = Http::timeout(20)
                ->retry(2, 300)
                ->get($this->baseUrl . $path, $query);

            if ($response->successful()) {
                return $response->json();
            }

            $status = $response->status();
            $body   = $response->json() ?? [];

            Log::warning("Datalastic: GET {$path} returned {$status}", [
                'query'    => array_diff_key($query, ['api-key' => '']), // omit key from logs
                'response' => $body,
            ]);

            if ($status === 404) {
                return ['error' => $body['message'] ?? 'Not found', 'status' => 404];
            }

            if ($status === 429) {
                return ['error' => 'Rate limit exceeded. Check your Datalastic plan.', 'status' => 429];
            }

            return ['error' => $body['message'] ?? 'Datalastic API error', 'status' => $status];
        } catch (\Throwable $e) {
            Log::error('Datalastic: Request failed', [
                'path'    => $path,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
