<?php

namespace App\Services\Tracking\Carriers;

interface CarrierTrackingInterface
{
    public function getCarrierScac(): string;

    public function getCarrierName(): string;

    public function trackByMBL(string $mblNumber): CarrierTrackingResponse;

    public function trackByContainer(string $containerNumber): CarrierTrackingResponse;

    public function trackByBooking(string $bookingNumber): CarrierTrackingResponse;

    public function getVesselSchedule(string $vesselName, ?string $voyage = null): array;

    public function supportsTracking(): bool;
}
