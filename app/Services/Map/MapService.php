<?php

namespace App\Services\Map;

use App\Models\Tenant\Container;
use App\Models\Tenant\Terminal;
use App\Models\Tenant\Vessel;
use Illuminate\Support\Collection;

class MapService
{
    public function getActiveVesselPositions(): Collection
    {
        return Vessel::query()
            ->whereNotNull('current_latitude')
            ->whereNotNull('current_longitude')
            ->whereNull('ata')
            ->select([
                'id',
                'name',
                'imo_number',
                'mmsi',
                'current_latitude',
                'current_longitude',
                'current_speed',
                'current_heading',
                'last_ais_update',
                'eta',
                'destination_port',
            ])
            ->get()
            ->map(function (Vessel $vessel) {
                return [
                    'id'           => $vessel->id,
                    'name'         => $vessel->name,
                    'imo'          => $vessel->imo_number,
                    'mmsi'         => $vessel->mmsi,
                    'latitude'     => (float) $vessel->current_latitude,
                    'longitude'    => (float) $vessel->current_longitude,
                    'speed'        => (float) $vessel->current_speed,
                    'heading'      => (float) $vessel->current_heading,
                    'last_update'  => $vessel->last_ais_update?->toIso8601String(),
                    'eta'          => $vessel->eta?->toIso8601String(),
                    'destination'  => $vessel->destination_port,
                    'type'         => 'vessel',
                ];
            });
    }

    public function getContainerLocations(): Collection
    {
        return Container::query()
            ->with(['vessel:id,current_latitude,current_longitude,name', 'mbl:id,port_of_discharge'])
            ->whereNotNull('status')
            ->whereNull('deleted_at')
            ->get()
            ->map(function (Container $container) {
                $location = $this->resolveContainerLocation($container);

                if ($location === null) {
                    return null;
                }

                return [
                    'id'             => $container->id,
                    'container_number' => $container->container_number,
                    'status'         => $container->status->value,
                    'latitude'       => $location['latitude'],
                    'longitude'      => $location['longitude'],
                    'location_name'  => $location['name'],
                    'type'           => 'container',
                ];
            })
            ->filter()
            ->values();
    }

    public function getPortLocations(): Collection
    {
        return Terminal::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->select(['id', 'name', 'code', 'city', 'country', 'latitude', 'longitude'])
            ->get()
            ->map(fn(Terminal $terminal) => [
                'id'        => $terminal->id,
                'name'      => $terminal->name,
                'code'      => $terminal->code,
                'city'      => $terminal->city,
                'country'   => $terminal->country,
                'latitude'  => (float) $terminal->latitude,
                'longitude' => (float) $terminal->longitude,
                'type'      => 'port',
            ]);
    }

    private function resolveContainerLocation(Container $container): ?array
    {
        // If container is on a vessel, use vessel's current position
        if ($container->vessel !== null
            && $container->vessel->current_latitude !== null
            && $container->vessel->current_longitude !== null) {
            return [
                'latitude'  => (float) $container->vessel->current_latitude,
                'longitude' => (float) $container->vessel->current_longitude,
                'name'      => 'Aboard ' . $container->vessel->name,
            ];
        }

        // If container has a terminal, look up terminal coordinates
        if (!empty($container->terminal_id)) {
            $terminal = Terminal::find($container->terminal_id);
            if ($terminal && $terminal->latitude !== null) {
                return [
                    'latitude'  => (float) $terminal->latitude,
                    'longitude' => (float) $terminal->longitude,
                    'name'      => $terminal->name,
                ];
            }
        }

        return null;
    }
}
