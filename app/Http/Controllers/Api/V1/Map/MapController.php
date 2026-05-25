<?php

namespace App\Http\Controllers\Api\V1\Map;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Container;
use App\Models\Tenant\Vessel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MapController extends Controller
{
    /**
     * GET /api/v1/map/vessels
     */
    public function vessels(Request $request): JsonResponse
    {
        $vessels = Vessel::query()
            ->whereNotNull('current_latitude')
            ->whereNotNull('current_longitude')
            ->select([
                'uuid', 'name', 'imo', 'mmsi', 'carrier_scac',
                'current_latitude', 'current_longitude',
                'current_port', 'destination_port',
                'eta', 'speed', 'heading',
                'updated_at',
            ])
            ->get()
            ->map(function ($vessel) {
                return [
                    'uuid'         => $vessel->uuid,
                    'name'         => $vessel->name,
                    'imo'          => $vessel->imo,
                    'mmsi'         => $vessel->mmsi,
                    'carrier_scac' => $vessel->carrier_scac,
                    'position'     => [
                        'lat' => (float) $vessel->current_latitude,
                        'lng' => (float) $vessel->current_longitude,
                    ],
                    'current_port'     => $vessel->current_port,
                    'destination_port' => $vessel->destination_port,
                    'eta'              => $vessel->eta,
                    'speed'            => $vessel->speed,
                    'heading'          => $vessel->heading,
                    'updated_at'       => $vessel->updated_at,
                ];
            });

        return $this->success(['vessels' => $vessels, 'total' => $vessels->count()]);
    }

    /**
     * GET /api/v1/map/containers
     */
    public function containers(Request $request): JsonResponse
    {
        $containers = Container::query()
            ->whereNotNull('vessel_uuid')
            ->whereHas('vessel', function ($q) {
                $q->whereNotNull('current_latitude')->whereNotNull('current_longitude');
            })
            ->with(['vessel:uuid,name,current_latitude,current_longitude'])
            ->select(['uuid', 'container_number', 'carrier_scac', 'vessel_uuid', 'status', 'eta', 'pod'])
            ->get()
            ->map(function ($container) {
                return [
                    'uuid'             => $container->uuid,
                    'container_number' => $container->container_number,
                    'carrier_scac'     => $container->carrier_scac,
                    'status'           => $container->status,
                    'eta'              => $container->eta,
                    'pod'              => $container->pod,
                    'vessel'           => $container->vessel ? [
                        'name'     => $container->vessel->name,
                        'position' => [
                            'lat' => (float) $container->vessel->current_latitude,
                            'lng' => (float) $container->vessel->current_longitude,
                        ],
                    ] : null,
                ];
            });

        return $this->success(['containers' => $containers, 'total' => $containers->count()]);
    }

    /**
     * GET /api/v1/map/ports
     */
    public function ports(Request $request): JsonResponse
    {
        // Return commonly tracked ports; in production this would query a ports table
        $ports = [
            ['locode' => 'USLAX', 'name' => 'Los Angeles',        'lat' => 33.7395, 'lng' => -118.2620, 'country' => 'US'],
            ['locode' => 'USLGB', 'name' => 'Long Beach',         'lat' => 33.7701, 'lng' => -118.1937, 'country' => 'US'],
            ['locode' => 'USNYC', 'name' => 'New York',           'lat' => 40.6643, 'lng' => -74.0102, 'country' => 'US'],
            ['locode' => 'USSAV', 'name' => 'Savannah',           'lat' => 32.0809, 'lng' => -81.0912, 'country' => 'US'],
            ['locode' => 'USHOU', 'name' => 'Houston',            'lat' => 29.7543, 'lng' => -95.3677, 'country' => 'US'],
            ['locode' => 'CNTAO', 'name' => 'Qingdao',            'lat' => 36.0671, 'lng' => 120.3826, 'country' => 'CN'],
            ['locode' => 'CNSHA', 'name' => 'Shanghai',           'lat' => 31.2304, 'lng' => 121.4737, 'country' => 'CN'],
            ['locode' => 'CNSGH', 'name' => 'Yantian',            'lat' => 22.5431, 'lng' => 114.2704, 'country' => 'CN'],
            ['locode' => 'CNNGB', 'name' => 'Ningbo',             'lat' => 29.8683, 'lng' => 121.5440, 'country' => 'CN'],
            ['locode' => 'SGSIN', 'name' => 'Singapore',          'lat' => 1.2644,  'lng' => 103.8222, 'country' => 'SG'],
            ['locode' => 'NLRTM', 'name' => 'Rotterdam',          'lat' => 51.9225, 'lng' => 4.4792,  'country' => 'NL'],
            ['locode' => 'DEHAM', 'name' => 'Hamburg',            'lat' => 53.5511, 'lng' => 9.9937,  'country' => 'DE'],
            ['locode' => 'KRPUS', 'name' => 'Busan',              'lat' => 35.1028, 'lng' => 129.0403, 'country' => 'KR'],
            ['locode' => 'JPTYO', 'name' => 'Tokyo',              'lat' => 35.6762, 'lng' => 139.6503, 'country' => 'JP'],
        ];

        if ($request->input('search')) {
            $search = strtolower($request->input('search'));
            $ports  = array_filter($ports, fn($p) =>
                str_contains(strtolower($p['name']), $search) ||
                str_contains(strtolower($p['locode']), $search)
            );
        }

        return $this->success(['ports' => array_values($ports), 'total' => count($ports)]);
    }
}
