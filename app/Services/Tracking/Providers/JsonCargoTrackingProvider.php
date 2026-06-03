<?php

declare(strict_types=1);

namespace App\Services\Tracking\Providers;

use App\Services\JsonCargo\JsonCargoService;
use App\Services\Tracking\Contracts\ContainerTrackingProvider;
use Illuminate\Support\Facades\Log;

/**
 * Wraps the existing JsonCargoService as a ContainerTrackingProvider.
 *
 * JSONCargo is a multi-carrier aggregator so it supports every carrier
 * (returns true from supports()). It is always tried first in the chain.
 */
class JsonCargoTrackingProvider implements ContainerTrackingProvider
{
    public function __construct(
        private readonly JsonCargoService $jsonCargo,
    ) {}

    public function name(): string
    {
        return 'jsoncargo';
    }

    public function isConfigured(): bool
    {
        return $this->jsonCargo->isConfigured();
    }

    /**
     * JSONCargo is a multi-carrier aggregator — it supports any carrier.
     */
    public function supports(?string $carrierScac): bool
    {
        return true;
    }

    /**
     * Call JsonCargoService and normalise the result.
     *
     * Returns null when:
     *  - The service is not configured.
     *  - The response is an error array (invalid key, 404, 429, etc.).
     *  - The response is null (network failure, timeout).
     *
     * When null is returned the caller captures the reason from
     * $this->lastError for use in the "attempted" log.
     */
    private ?string $lastError = null;

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function track(string $containerNumber, ?string $carrierScac = null, ?string $bol = null): ?array
    {
        $this->lastError = null;

        if (! $this->isConfigured()) {
            $this->lastError = 'not configured — add JSONCARGO_API_KEY to .env';
            return null;
        }

        // Resolve shipping line hint for JSONCargo from the SCAC if available
        $shippingLine = null;
        if ($carrierScac !== null) {
            $shippingLine = $this->jsonCargo->resolveShippingLine($carrierScac);
        }

        $data = $this->jsonCargo->getContainerDetails($containerNumber, $shippingLine);

        // Service returned null — network/timeout failure
        if ($data === null) {
            $this->lastError = 'request failed (timeout or network error)';
            return null;
        }

        // Service returned an error structure from the API
        if (isset($data['error'])) {
            $status = $data['status'] ?? 'unknown';
            $this->lastError = "API error {$status}: {$data['error']}";

            Log::debug('JsonCargoTrackingProvider: JSONCargo returned error', [
                'container' => $containerNumber,
                'error'     => $data,
            ]);

            return null;
        }

        // Valid data — attach source tag and normalise events array
        return array_merge($data, [
            'source' => $this->name(),
            'events' => $data['events'] ?? [],
        ]);
    }
}
