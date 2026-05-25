<?php

namespace App\Services\Carrier;

use App\Models\Tenant\CarrierApiCredential;
use App\Services\Tracking\Carriers\CarrierRegistry;
use App\Services\Tracking\Carriers\CarrierTrackerFactory;
use App\Services\Tracking\Carriers\CarrierTrackingInterface;
use App\Services\Tracking\Carriers\CarrierTrackingResponse;
use Illuminate\Support\Facades\RateLimiter;

class CarrierApiManager
{
    private const RATE_LIMITS = [
        'MAEU' => ['per_second' => 10, 'per_minute' => 200],
        'SUDU' => ['per_second' => 10, 'per_minute' => 200],
        'MSCU' => ['per_second' => 5,  'per_minute' => 100],
        'CMDU' => ['per_second' => 5,  'per_minute' => 100],
        'COSU' => ['per_second' => 5,  'per_minute' => 100],
        'HLCU' => ['per_second' => 3,  'per_minute' => 60],
        'ONEY' => ['per_second' => 5,  'per_minute' => 100],
        'EGLV' => ['per_second' => 5,  'per_minute' => 100],
        'HDMU' => ['per_second' => 5,  'per_minute' => 100],
        'YMLU' => ['per_second' => 5,  'per_minute' => 100],
        'ZIMU' => ['per_second' => 5,  'per_minute' => 100],
        'WHLC' => ['per_second' => 5,  'per_minute' => 100],
        'PILU' => ['per_second' => 5,  'per_minute' => 100],
        'KMTU' => ['per_second' => 5,  'per_minute' => 100],
        'XPRU' => ['per_second' => 5,  'per_minute' => 100],
    ];

    public function __construct(
        private readonly CarrierTrackerFactory $factory,
    ) {}

    /**
     * Get the configured tracker for a carrier, injecting per-tenant credentials if available.
     */
    public function tracker(string $scac): CarrierTrackingInterface
    {
        $resolvedScac = CarrierRegistry::resolveScac($scac);
        $tracker      = $this->factory->make($resolvedScac);

        $credential = $this->loadCredential($resolvedScac);
        if ($credential && method_exists($tracker, 'setCredentials')) {
            $tracker->setCredentials($credential);
        }

        return $tracker;
    }

    /**
     * Track a container with rate limiting.
     */
    public function trackContainer(string $scac, string $containerNumber): CarrierTrackingResponse
    {
        $resolvedScac = CarrierRegistry::resolveScac($scac);
        $this->checkRateLimit($resolvedScac);

        $tracker  = $this->tracker($resolvedScac);
        $response = $tracker->trackByContainer($containerNumber);

        $this->touchCredential($resolvedScac);

        return $response;
    }

    /**
     * Track by MBL with rate limiting.
     */
    public function trackMBL(string $scac, string $mblNumber): CarrierTrackingResponse
    {
        $resolvedScac = CarrierRegistry::resolveScac($scac);
        $this->checkRateLimit($resolvedScac);

        $tracker  = $this->tracker($resolvedScac);
        $response = $tracker->trackByMBL($mblNumber);

        $this->touchCredential($resolvedScac);

        return $response;
    }

    /**
     * Track by booking with rate limiting.
     */
    public function trackBooking(string $scac, string $bookingNumber): CarrierTrackingResponse
    {
        $resolvedScac = CarrierRegistry::resolveScac($scac);
        $this->checkRateLimit($resolvedScac);

        $tracker  = $this->tracker($resolvedScac);
        $response = $tracker->trackByBooking($bookingNumber);

        $this->touchCredential($resolvedScac);

        return $response;
    }

    /**
     * Get vessel schedule with rate limiting.
     */
    public function getVesselSchedule(string $scac, string $vesselName, ?string $voyage = null): array
    {
        $resolvedScac = CarrierRegistry::resolveScac($scac);
        $this->checkRateLimit($resolvedScac);

        $tracker = $this->tracker($resolvedScac);
        return $tracker->getVesselSchedule($vesselName, $voyage);
    }

    /**
     * Check if a carrier has credentials configured (tenant or global).
     */
    public function hasCredentials(string $scac): bool
    {
        $resolvedScac = CarrierRegistry::resolveScac($scac);

        $credential = $this->loadCredential($resolvedScac);
        if ($credential) return true;

        $configKey = $this->getConfigKeyForScac($resolvedScac);
        return !empty(config("carriers.{$configKey}.api_key"))
            || !empty(config("carriers.{$configKey}.consumer_key"));
    }

    /**
     * Get all carriers with their connection status for the current tenant.
     */
    public function getCarrierStatuses(): array
    {
        $carriers = [
            // Top 15 global steamship lines by capacity
            ['scac' => 'MSCU', 'name' => 'MSC',              'group' => 'Mediterranean Shipping Company',  'auth_type' => 'api_key'],
            ['scac' => 'MAEU', 'name' => 'Maersk',           'group' => 'Maersk Group',                    'auth_type' => 'consumer_key'],
            ['scac' => 'CMDU', 'name' => 'CMA CGM',          'group' => 'CMA CGM Group',                   'auth_type' => 'api_key'],
            ['scac' => 'COSU', 'name' => 'COSCO Shipping',   'group' => 'COSCO Group',                     'auth_type' => 'api_key'],
            ['scac' => 'HLCU', 'name' => 'Hapag-Lloyd',      'group' => 'Hapag-Lloyd AG',                  'auth_type' => 'oauth2'],
            ['scac' => 'ONEY', 'name' => 'ONE',              'group' => 'Ocean Network Express',           'auth_type' => 'api_key'],
            ['scac' => 'EGLV', 'name' => 'Evergreen',        'group' => 'Evergreen Marine',                'auth_type' => 'api_key'],
            ['scac' => 'HDMU', 'name' => 'HMM',              'group' => 'Hyundai Merchant Marine',         'auth_type' => 'api_key'],
            ['scac' => 'YMLU', 'name' => 'Yang Ming',        'group' => 'Yang Ming Marine Transport',      'auth_type' => 'api_key'],
            ['scac' => 'ZIMU', 'name' => 'ZIM',              'group' => 'ZIM Integrated Shipping',         'auth_type' => 'api_key'],
            ['scac' => 'WHLC', 'name' => 'Wan Hai Lines',    'group' => 'Wan Hai Lines',                   'auth_type' => 'api_key'],
            ['scac' => 'PILU', 'name' => 'PIL',              'group' => 'Pacific International Lines',     'auth_type' => 'api_key'],
            ['scac' => 'SUDU', 'name' => 'Hamburg Süd',      'group' => 'Maersk Group',                    'auth_type' => 'consumer_key'],
            ['scac' => 'KMTU', 'name' => 'SM Line',          'group' => 'SM Line Corporation',             'auth_type' => 'api_key'],
            ['scac' => 'XPRU', 'name' => 'X-Press Feeders',  'group' => 'X-Press Feeders Group',           'auth_type' => 'api_key'],
        ];

        $credentials = CarrierApiCredential::whereIn('carrier_scac', array_column($carriers, 'scac'))
            ->get()
            ->keyBy('carrier_scac');

        return array_map(function ($carrier) use ($credentials) {
            $cred = $credentials->get($carrier['scac']);
            return array_merge($carrier, [
                'connected'     => $cred !== null && $cred->is_active,
                'environment'   => $cred?->environment ?? 'production',
                'last_used_at'  => $cred?->last_used_at?->toIso8601String(),
                'credential_id' => $cred?->id,
            ]);
        }, $carriers);
    }

    /**
     * List all supported carrier SCACs.
     */
    public function supportedScacs(): array
    {
        return $this->factory->supportedScacs();
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

    private function loadCredential(string $scac): ?CarrierApiCredential
    {
        try {
            return CarrierApiCredential::where('carrier_scac', $scac)
                ->where('is_active', true)
                ->first();
        } catch (\Exception $e) {
            // No tenant context or table doesn't exist yet
            return null;
        }
    }

    private function touchCredential(string $scac): void
    {
        try {
            CarrierApiCredential::where('carrier_scac', $scac)
                ->update(['last_used_at' => now()]);
        } catch (\Exception $e) {
            // Ignore
        }
    }

    private function checkRateLimit(string $scac): void
    {
        $limits = self::RATE_LIMITS[$scac] ?? ['per_second' => 5, 'per_minute' => 100];
        $key    = "carrier_api:{$scac}";

        if (RateLimiter::tooManyAttempts("{$key}:min", $limits['per_minute'])) {
            $retryAfter = RateLimiter::availableIn("{$key}:min");
            throw new \RuntimeException("Rate limit exceeded for {$scac}. Retry after {$retryAfter} seconds.");
        }

        RateLimiter::hit("{$key}:min", 60);
    }

    private function getConfigKeyForScac(string $scac): string
    {
        return match($scac) {
            'MAEU', 'SUDU', 'SAFI' => 'maersk',
            'MSCU'                  => 'msc',
            'CMDU'                  => 'cma_cgm',
            'COSU', 'CLHU'          => 'cosco',
            'HLCU'                  => 'hapag_lloyd',
            'ONEY', 'KKLU', 'NYKU', 'MOLU' => 'one',
            'EGLV'                  => 'evergreen',
            'HDMU'                  => 'hmm',
            'YMLU'                  => 'yang_ming',
            'ZIMU'                  => 'zim',
            'WHLC'                  => 'wan_hai',
            'PILU'                  => 'pil',
            'KMTU'                  => 'sm_line',
            'XPRU'                  => 'x_press_feeders',
            default                 => strtolower($scac),
        };
    }
}
