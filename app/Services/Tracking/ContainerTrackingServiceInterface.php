<?php

namespace App\Services\Tracking;

use App\Models\Tenant\Container;
use App\Models\Tenant\TrackingRequest;
use App\Domain\Container\Enums\ContainerStatus;
use Illuminate\Support\Collection;

interface ContainerTrackingServiceInterface
{
    public function createTrackingRequest(array $data): TrackingRequest;

    public function pollCarrierUpdates(TrackingRequest $request): void;

    public function updateContainerStatus(Container $container, ContainerStatus $newStatus, array $eventData = []): void;

    public function getContainerTimeline(Container $container): Collection;

    public function detectCarrierFromMBL(string $mblNumber): ?string;
}
