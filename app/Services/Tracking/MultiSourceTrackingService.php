<?php

declare(strict_types=1);

namespace App\Services\Tracking;

use App\Services\Tracking\Providers\DcsaTrackingProvider;
use App\Services\Tracking\Providers\JsonCargoTrackingProvider;
use Illuminate\Support\Facades\Log;

/**
 * Multi-source container tracking resolver.
 *
 * Chain:
 *  1. Try JSONCargo (multi-carrier aggregator).  Source tag: 'jsoncargo'.
 *  2. If JSONCargo returns no usable data, resolve the carrier via the
 *     CarrierApiRegistry and try the carrier's own DCSA endpoint.
 *  3. If nothing works, return a structured "not found" response that
 *     describes every source attempted and the reason for each failure.
 *
 * This service NEVER throws — every error is captured in the returned array.
 */
class MultiSourceTrackingService
{
    public function __construct(
        private readonly JsonCargoTrackingProvider $jsonCargoProvider,
        private readonly CarrierApiRegistry        $registry,
    ) {}

    /**
     * Track a container through the provider chain.
     *
     * @param  string      $containerNumber  ISO container number (e.g. ZCSU7244544)
     * @param  string|null $carrierScac      Optional explicit carrier SCAC
     * @param  string|null $bol              Optional Bill of Lading number
     * @return array<string,mixed>  Always a structured result (never null, never throws)
     */
    public function track(string $containerNumber, ?string $carrierScac = null, ?string $bol = null): array
    {
        $containerNumber = strtoupper(trim($containerNumber));
        $attempted       = [];

        // ------------------------------------------------------------------
        // 1. Try JSONCargo
        // ------------------------------------------------------------------
        $jsonCargoResult = null;

        try {
            $jsonCargoResult = $this->jsonCargoProvider->track($containerNumber, $carrierScac, $bol);
        } catch (\Throwable $e) {
            Log::error('MultiSourceTrackingService: JsonCargo provider threw', [
                'container' => $containerNumber,
                'error'     => $e->getMessage(),
            ]);
        }

        if ($jsonCargoResult !== null) {
            return array_merge(['found' => true], $jsonCargoResult);
        }

        $attempted[] = [
            'provider' => 'jsoncargo',
            'reason'   => $this->jsonCargoProvider->getLastError()
                ?? 'no data returned',
        ];

        // ------------------------------------------------------------------
        // 2. Resolve carrier and try DCSA direct endpoint
        // ------------------------------------------------------------------
        $resolvedScac = $this->resolveScac($carrierScac, $bol, $containerNumber);

        if ($resolvedScac === null) {
            $attempted[] = [
                'provider' => 'dcsa_direct',
                'reason'   => 'carrier could not be determined from container prefix, BOL prefix, or explicit param',
            ];

            return $this->notFound($containerNumber, $attempted);
        }

        $carrierConfig = $this->registry->forScac($resolvedScac);

        if ($carrierConfig === null) {
            $attempted[] = [
                'provider' => strtolower($resolvedScac),
                'reason'   => "carrier {$resolvedScac} not found in services.carriers config",
            ];

            return $this->notFound($containerNumber, $attempted);
        }

        $dcsaProvider = new DcsaTrackingProvider($carrierConfig);
        $dcsaResult   = null;

        try {
            $dcsaResult = $dcsaProvider->track($containerNumber, $resolvedScac, $bol);
        } catch (\Throwable $e) {
            Log::error('MultiSourceTrackingService: DCSA provider threw', [
                'container' => $containerNumber,
                'carrier'   => $resolvedScac,
                'error'     => $e->getMessage(),
            ]);
        }

        if ($dcsaResult !== null) {
            return array_merge(['found' => true], $dcsaResult);
        }

        $attempted[] = [
            'provider' => $dcsaProvider->name(),
            'reason'   => $dcsaProvider->getLastError() ?? 'no data returned',
        ];

        return $this->notFound($containerNumber, $attempted);
    }

    /**
     * Returns the list of all configured/unconfigured providers for the
     * /tracking/sources endpoint.
     *
     * @return array<int,array<string,mixed>>
     */
    public function providerSources(): array
    {
        $sources = [
            [
                'provider'   => 'jsoncargo',
                'type'       => 'aggregator',
                'configured' => $this->jsonCargoProvider->isConfigured(),
                'carriers'   => 'all (multi-carrier aggregator)',
            ],
        ];

        foreach ($this->registry->allCarriers() as $carrier) {
            $sources[] = [
                'provider'   => $carrier['key'],
                'type'       => 'dcsa_direct',
                'configured' => $carrier['configured'],
                'scac'       => $carrier['scac'],
                'name'       => $carrier['name'],
            ];
        }

        return $sources;
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

    /**
     * Resolve the best SCAC to use for DCSA fallback, in priority order:
     *  1. Explicit param
     *  2. Inferred from BOL prefix
     *  3. Inferred from container prefix
     */
    private function resolveScac(?string $explicit, ?string $bol, string $containerNumber): ?string
    {
        if (! empty($explicit)) {
            return strtoupper($explicit);
        }

        if (! empty($bol)) {
            $fromBol = $this->registry->scacFromBol($bol);
            if ($fromBol !== null) {
                return $fromBol;
            }
        }

        return $this->registry->scacFromContainerPrefix($containerNumber);
    }

    /**
     * Build the standardised "not found" response.
     *
     * @param  array<int,array<string,string>> $attempted
     * @return array<string,mixed>
     */
    private function notFound(string $containerNumber, array $attempted): array
    {
        return [
            'found'       => false,
            'container'   => $containerNumber,
            'attempted'   => $attempted,
            'message'     => 'No tracking data found. See attempted[] for details on each provider.',
        ];
    }
}
