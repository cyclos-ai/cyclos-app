<?php

namespace App\Http\Controllers\Api\V1\Order;

use App\Http\Controllers\Controller;
use App\Http\Resources\MBL\MBLResource;
use App\Models\Tenant\Container;
use App\Models\Tenant\MBL;
use App\Services\Vessel\VesselLinkingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderImportController extends Controller
{
    /**
     * POST /api/v1/orders/import
     *
     * Transactionally create an MBL, its containers, and link the vessel in a
     * single atomic call. Backs the order-entry wizard.
     */
    public function import(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mbl.mbl_number'        => ['required', 'string', 'max:100'],
            'mbl.carrier_scac'      => ['nullable', 'string', 'max:10'],
            'mbl.pol'               => ['nullable', 'string', 'max:100'],
            'mbl.pod'               => ['nullable', 'string', 'max:100'],
            'mbl.eta'               => ['nullable', 'date'],
            'mbl.etd'               => ['nullable', 'date'],
            'mbl.shipper_name'      => ['nullable', 'string', 'max:255'],
            'mbl.consignee_name'    => ['nullable', 'string', 'max:255'],
            'mbl.notify_party'      => ['nullable', 'string', 'max:255'],

            'vessel.vessel_name'    => ['nullable', 'string', 'max:255'],
            'vessel.imo'            => ['nullable', 'string', 'max:20'],
            'vessel.mmsi'           => ['nullable', 'string', 'max:20'],
            'vessel.voyage_number'  => ['nullable', 'string', 'max:50'],

            'containers'                    => ['nullable', 'array'],
            'containers.*.container_number' => ['required_with:containers', 'string', 'max:20'],
            'containers.*.type'             => ['nullable', 'string', 'max:20'],
            'containers.*.size'             => ['nullable', 'string', 'max:20'],
            'containers.*.weight_kg'        => ['nullable', 'numeric'],
        ]);

        $mblData    = $validated['mbl'];
        $vesselData = $validated['vessel'] ?? [];
        $containers = $validated['containers'] ?? [];

        $mbl = DB::transaction(function () use ($mblData, $vesselData, $containers) {
            $orgId = tenancy()->tenant?->id;

            // --- Vessel: find or create when identifying fields are present ---
            $vesselId = null;
            $hasVesselInfo = ! empty($vesselData['vessel_name'])
                || ! empty($vesselData['imo'])
                || ! empty($vesselData['mmsi']);

            if ($hasVesselInfo) {
                $vessel = app(VesselLinkingService::class)->findOrCreateVessel([
                    'vessel_name'   => $vesselData['vessel_name']   ?? null,
                    'imo'           => $vesselData['imo']           ?? null,
                    'mmsi'          => $vesselData['mmsi']          ?? null,
                    'voyage_number' => $vesselData['voyage_number'] ?? null,
                    'carrier_scac'  => $mblData['carrier_scac']     ?? null,
                ]);
                $vesselId = $vessel?->id;
            }

            // --- MBL ---
            $mbl = MBL::create([
                'organization_id' => $orgId,
                'mbl_number'      => $mblData['mbl_number'],
                'carrier_scac'    => $mblData['carrier_scac']   ?? null,
                'vessel_id'       => $vesselId,
                'pol'             => $mblData['pol']             ?? null,
                'pod'             => $mblData['pod']             ?? null,
                'eta'             => $mblData['eta']             ?? null,
                'etd'             => $mblData['etd']             ?? null,
                'shipper_name'    => $mblData['shipper_name']    ?? null,
                'consignee_name'  => $mblData['consignee_name']  ?? null,
                'notify_party'    => $mblData['notify_party']    ?? null,
                'status'          => 'active',
            ]);

            // --- Containers ---
            $count = 0;
            foreach ($containers as $c) {
                Container::updateOrCreate(
                    [
                        'organization_id'  => $orgId,
                        'container_number' => strtoupper(trim($c['container_number'])),
                    ],
                    [
                        'mbl_id'       => $mbl->id,
                        'vessel_id'    => $vesselId,
                        'carrier_scac' => $mblData['carrier_scac'] ?? null,
                        'type'         => $c['type']      ?? null,
                        'size'         => $c['size']      ?? null,
                        'weight'       => $c['weight_kg'] ?? null,
                        'pol'          => $mblData['pol'] ?? null,
                        'pod'          => $mblData['pod'] ?? null,
                        'eta'          => $mblData['eta'] ?? null,
                        'status'       => 'NOT_TRACKING',
                    ]
                );
                $count++;
            }

            $mbl->update(['container_count' => $count]);

            return $mbl;
        });

        $count = $mbl->container_count;

        return $this->created(
            new MBLResource($mbl->fresh()->load(['vessel', 'containers'])),
            "Order imported: MBL + {$count} container(s)"
        );
    }
}
