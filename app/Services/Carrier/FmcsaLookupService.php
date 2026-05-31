<?php

namespace App\Services\Carrier;

use App\Services\Tracking\Carriers\CarrierRegistry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class FmcsaLookupService
{
    private ?string $webKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->webKey = config('services.fmcsa.web_key');
        $this->baseUrl = config('services.fmcsa.base_url', 'https://mobile.fmcsa.dot.gov/qc/services/carriers');
    }

    /**
     * Look up carrier data by SCAC code.
     *
     * Tries FMCSA API first (if key configured), then falls back
     * to the internal CarrierRegistry for ocean carriers.
     */
    public function lookupByScac(string $scac): ?array
    {
        $scac = strtoupper(trim($scac));

        $cacheKey = "fmcsa_scac_{$scac}";
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        // Try FMCSA API search by name if key is configured
        if ($this->webKey) {
            $result = $this->searchFmcsaByName($scac);
            if ($result) {
                $result['scac'] = $scac;
                Cache::put($cacheKey, $result, now()->addHours(24));
                return $result;
            }
        }

        // Fallback: check our internal CarrierRegistry (ocean carriers)
        $registryData = CarrierRegistry::getCarrier($scac);
        if ($registryData) {
            $result = [
                'company_name' => $registryData['name'],
                'scac'         => $scac,
                'usdot'        => null,
                'mc_number'    => null,
                'address'      => null,
                'city'         => null,
                'state'        => null,
                'zip'          => null,
                'contact_phone' => null,
                'fleet_size'   => null,
                'source'       => 'carrier_registry',
            ];
            Cache::put($cacheKey, $result, now()->addHours(24));
            return $result;
        }

        return null;
    }

    /**
     * Look up carrier data by USDOT number.
     * This is the most reliable FMCSA lookup method.
     */
    public function lookupByUsdot(string $usdot): ?array
    {
        $usdot = trim($usdot);

        $cacheKey = "fmcsa_usdot_{$usdot}";
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        if (! $this->webKey) {
            return null;
        }

        try {
            $response = Http::timeout(10)
                ->accept('application/json')
                ->get("{$this->baseUrl}/{$usdot}", [
                    'webKey' => $this->webKey,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $carrier = $data['content']['carrier'] ?? null;

                if ($carrier) {
                    $result = $this->mapFmcsaResponse($carrier);
                    Cache::put($cacheKey, $result, now()->addHours(24));
                    return $result;
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return null;
    }

    /**
     * Search FMCSA by carrier name.
     */
    private function searchFmcsaByName(string $name): ?array
    {
        try {
            $response = Http::timeout(10)
                ->accept('application/json')
                ->get("{$this->baseUrl}/name/{$name}", [
                    'webKey' => $this->webKey,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $carriers = $data['content'] ?? [];

                if (! empty($carriers)) {
                    return $this->mapFmcsaResponse($carriers[0]);
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return null;
    }

    /**
     * Normalize a raw FMCSA API response into a standard array.
     */
    private function mapFmcsaResponse(array $carrier): array
    {
        return [
            'company_name'  => $carrier['legalName'] ?? $carrier['dbaName'] ?? null,
            'scac'          => null, // FMCSA does not return SCAC codes
            'usdot'         => isset($carrier['dotNumber']) ? (string) $carrier['dotNumber'] : null,
            'mc_number'     => $carrier['mcNumber'] ?? null,
            'address'       => $carrier['phyStreet'] ?? null,
            'city'          => $carrier['phyCity'] ?? null,
            'state'         => $carrier['phyState'] ?? null,
            'zip'           => $carrier['phyZipcode'] ?? null,
            'contact_phone' => $carrier['telephone'] ?? null,
            'fleet_size'    => isset($carrier['totalDrivers']) ? (int) $carrier['totalDrivers'] : null,
            'source'        => 'fmcsa',
        ];
    }
}
