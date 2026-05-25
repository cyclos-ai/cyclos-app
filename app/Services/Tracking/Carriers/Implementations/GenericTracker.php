<?php

namespace App\Services\Tracking\Carriers\Implementations;

use App\Services\Tracking\Carriers\CarrierRegistry;
use App\Services\Tracking\Carriers\CarrierTrackingResponse;

/**
 * Generic / fallback tracker for carriers without a dedicated API integration.
 * Returns a structured "tracking not available" response that includes carrier
 * info so the caller can direct the user to the carrier website.
 */
class GenericTracker extends AbstractCarrierTracker
{
    private string $scac;

    public function __construct(string $scac)
    {
        $this->scac = strtoupper($scac);
        parent::__construct();
    }

    protected function resolveApiKey(): string
    {
        return '';
    }

    protected function resolveBaseUrl(): string
    {
        $carrier = CarrierRegistry::getCarrier($this->scac);
        return $carrier['tracking_url'] ?? $carrier['website'] ?? 'https://example.com';
    }

    protected function defaultHeaders(): array
    {
        return ['Accept' => 'application/json'];
    }

    public function getCarrierScac(): string
    {
        return $this->scac;
    }

    public function getCarrierName(): string
    {
        $carrier = CarrierRegistry::getCarrier($this->scac);
        return $carrier['name'] ?? $this->scac;
    }

    public function supportsTracking(): bool
    {
        $carrier = CarrierRegistry::getCarrier($this->scac);
        return !empty($carrier['tracking_url']);
    }

    public function trackByMBL(string $mblNumber): CarrierTrackingResponse
    {
        return $this->notAvailableResponse($mblNumber);
    }

    public function trackByContainer(string $containerNumber): CarrierTrackingResponse
    {
        return $this->notAvailableResponse($containerNumber);
    }

    public function trackByBooking(string $bookingNumber): CarrierTrackingResponse
    {
        return $this->notAvailableResponse($bookingNumber);
    }

    public function getVesselSchedule(string $vesselName, ?string $voyage = null): array
    {
        return [];
    }

    private function notAvailableResponse(string $reference): CarrierTrackingResponse
    {
        $carrier = CarrierRegistry::getCarrier($this->scac);
        $name    = $carrier['name'] ?? $this->scac;
        $website = $carrier['website'] ?? null;

        $message = "Direct API tracking is not yet available for {$name} ({$this->scac}).";
        if ($website) {
            $message .= " Track manually at: {$website}";
        }

        return CarrierTrackingResponse::failure($message);
    }
}
