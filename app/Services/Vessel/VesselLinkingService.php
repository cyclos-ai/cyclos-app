<?php

namespace App\Services\Vessel;

use App\Models\Tenant\Container;
use App\Models\Tenant\Vessel;
use App\Services\JsonCargo\JsonCargoService;
use Illuminate\Support\Facades\Log;

class VesselLinkingService
{
    public function __construct(
        private readonly JsonCargoService $jsonCargoService,
    ) {}

    /**
     * Find or create a Vessel record from the given identifying data.
     *
     * Lookup priority:
     *  1. imo_number (most reliable unique key)
     *  2. vessel name (case-insensitive)
     *
     * @param  array{vessel_name?: string|null, imo?: string|null, mmsi?: string|null, voyage_number?: string|null, carrier_scac?: string|null} $data
     */
    public function findOrCreateVessel(array $data): ?Vessel
    {
        $name         = $data['vessel_name'] ?? null;
        $imo          = $data['imo']          ?? null;
        $mmsi         = $data['mmsi']         ?? null;
        $voyageNumber = $data['voyage_number'] ?? null;
        $carrierScac  = $data['carrier_scac'] ?? null;

        if (! $imo && ! $name) {
            return null;
        }

        $organizationId = tenancy()->tenant?->id;

        // --- Lookup or create by IMO (most reliable) ---
        if ($imo) {
            $vessel = Vessel::where('imo_number', $imo)->first();

            if ($vessel) {
                $this->patchMissingFields($vessel, $name, $mmsi, $voyageNumber, $carrierScac);

                return $vessel;
            }

            // Create new vessel keyed on IMO
            $vessel = Vessel::create(array_filter([
                'organization_id' => $organizationId,
                'name'            => $name ?? ('Vessel IMO ' . $imo),
                'imo_number'      => $imo,
                'mmsi'            => $mmsi,
                'voyage_number'   => $voyageNumber,
                'carrier_scac'    => $carrierScac,
            ], fn ($v) => $v !== null));

            $this->enrichVesselFromJsonCargo($vessel);

            return $vessel;
        }

        // --- Lookup or create by name ---
        $vessel = Vessel::whereRaw('LOWER(name) = ?', [strtolower($name)])->first();

        if ($vessel) {
            $this->patchMissingFields($vessel, $name, $mmsi, $voyageNumber, $carrierScac);

            return $vessel;
        }

        $vessel = Vessel::create(array_filter([
            'organization_id' => $organizationId,
            'name'            => $name,
            'imo_number'      => $imo,
            'mmsi'            => $mmsi,
            'voyage_number'   => $voyageNumber,
            'carrier_scac'    => $carrierScac,
        ], fn ($v) => $v !== null));

        return $vessel;
    }

    /**
     * Find or create a vessel from the given data, set vessel_id on the
     * container, save it, and return the vessel.
     */
    public function linkContainerToVessel(Container $container, array $vesselData): ?Vessel
    {
        $vessel = $this->findOrCreateVessel($vesselData);

        if (! $vessel) {
            return null;
        }

        $container->vessel_id = $vessel->id;
        $container->save();

        return $vessel;
    }

    /**
     * Attempt to enrich the vessel record with live data from JSONCargo.
     * Non-fatal — any exception is swallowed and logged.
     */
    public function enrichVesselFromJsonCargo(Vessel $vessel): void
    {
        if (! $vessel->imo_number) {
            return;
        }

        if (! $this->jsonCargoService->isConfigured()) {
            return;
        }

        try {
            $data = $this->jsonCargoService->getVesselBasic(['imo' => $vessel->imo_number]);

            if (! $data || isset($data['error'])) {
                return;
            }

            $updates = [];

            if (empty($vessel->mmsi) && ! empty($data['mmsi'])) {
                $updates['mmsi'] = $data['mmsi'];
            }

            if (empty($vessel->name) && ! empty($data['name'])) {
                $updates['name'] = $data['name'];
            }

            if (! empty($updates)) {
                $vessel->update($updates);
            }
        } catch (\Throwable $e) {
            Log::warning('VesselLinkingService: JSONCargo enrichment failed', [
                'imo'     => $vessel->imo_number,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Back-fill any nullable fields on an existing vessel that are now
     * available from the inbound data.
     */
    private function patchMissingFields(
        Vessel $vessel,
        ?string $name,
        ?string $mmsi,
        ?string $voyageNumber,
        ?string $carrierScac,
    ): void {
        $updates = [];

        if ($name && empty($vessel->name)) {
            $updates['name'] = $name;
        }

        if ($mmsi && empty($vessel->mmsi)) {
            $updates['mmsi'] = $mmsi;
        }

        if ($voyageNumber && empty($vessel->voyage_number)) {
            $updates['voyage_number'] = $voyageNumber;
        }

        if ($carrierScac && empty($vessel->carrier_scac)) {
            $updates['carrier_scac'] = $carrierScac;
        }

        if (! empty($updates)) {
            $vessel->update($updates);
        }
    }
}
