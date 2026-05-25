<?php

namespace App\Http\Controllers\Api\V1\Air;

use App\Http\Controllers\Controller;
use App\Models\Tenant\AirShipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AirShipmentController extends Controller
{
    /**
     * GET /api/v1/air-shipments
     */
    public function index(Request $request): JsonResponse
    {
        $query = AirShipment::query();

        if ($request->has('filters')) {
            $this->applyFilters($query, $request->input('filters', []));
        }

        $this->applySorting(
            $query,
            $request->input('order_by', 'created_at'),
            (int) $request->input('direction', -1)
        );

        return $this->paginateResource($query, $request, \App\Http\Resources\Air\AirShipmentResource::class);
    }

    /**
     * POST /api/v1/air-shipments
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'awb_number'    => 'required|string|max:20',
            'carrier_code'  => 'required|string|max:3',
            'origin'        => 'required|string|max:3',
            'destination'   => 'required|string|max:3',
            'shipper'       => 'nullable|string|max:255',
            'consignee'     => 'nullable|string|max:255',
            'weight_kg'     => 'nullable|numeric|min:0',
            'pieces'        => 'nullable|integer|min:1',
            'commodity'     => 'nullable|string|max:255',
        ]);

        $shipment = AirShipment::create($request->validated());

        return $this->created($shipment, 'Air shipment created');
    }

    /**
     * GET /api/v1/air-shipments/awb/{awb_number}
     */
    public function showByAwb(string $awb): JsonResponse
    {
        $shipment = AirShipment::where('awb_number', $awb)->first();

        if (! $shipment) {
            return $this->notFound('Air shipment not found');
        }

        return $this->success($shipment);
    }

    /**
     * GET /api/v1/air-shipments/supported-carriers
     */
    public function supportedCarriers(Request $request): JsonResponse
    {
        $carriers = [
            ['code' => 'AA',  'name' => 'American Airlines Cargo'],
            ['code' => 'AC',  'name' => 'Air Canada Cargo'],
            ['code' => 'AF',  'name' => 'Air France Cargo'],
            ['code' => 'AZ',  'name' => 'ITA Airways Cargo'],
            ['code' => 'BA',  'name' => 'British Airways World Cargo'],
            ['code' => 'CA',  'name' => 'Air China Cargo'],
            ['code' => 'CI',  'name' => 'China Airlines Cargo'],
            ['code' => 'CX',  'name' => 'Cathay Pacific Cargo'],
            ['code' => 'DL',  'name' => 'Delta Cargo'],
            ['code' => 'EK',  'name' => 'Emirates SkyCargo'],
            ['code' => 'EY',  'name' => 'Etihad Cargo'],
            ['code' => 'FX',  'name' => 'FedEx'],
            ['code' => 'IB',  'name' => 'Iberia Cargo'],
            ['code' => 'JL',  'name' => 'Japan Airlines Cargo'],
            ['code' => 'KE',  'name' => 'Korean Air Cargo'],
            ['code' => 'KL',  'name' => 'Air France-KLM Cargo'],
            ['code' => 'LH',  'name' => 'Lufthansa Cargo'],
            ['code' => 'MH',  'name' => 'Malaysia Airlines'],
            ['code' => 'NH',  'name' => 'ANA Cargo'],
            ['code' => 'QR',  'name' => 'Qatar Airways Cargo'],
            ['code' => 'SQ',  'name' => 'Singapore Airlines Cargo'],
            ['code' => 'SV',  'name' => 'Saudia Cargo'],
            ['code' => 'TK',  'name' => 'Turkish Airlines Cargo'],
            ['code' => 'TZ',  'name' => 'ATA Airlines'],
            ['code' => 'UA',  'name' => 'United Airlines Cargo'],
            ['code' => 'UPS', 'name' => 'UPS Airlines'],
        ];

        return $this->success($carriers);
    }
}
