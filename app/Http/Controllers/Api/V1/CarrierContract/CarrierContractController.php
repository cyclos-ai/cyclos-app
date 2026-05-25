<?php

namespace App\Http\Controllers\Api\V1\CarrierContract;

use App\Http\Controllers\Controller;
use App\Http\Requests\CarrierContract\StoreCarrierContractRequest;
use App\Http\Resources\CarrierContract\CarrierContractResource;
use App\Models\Tenant\CarrierContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CarrierContractController extends Controller
{
    /**
     * GET /api/v1/carrier-contracts/spot
     */
    public function spotContracts(Request $request): JsonResponse
    {
        $query = CarrierContract::where('contract_type', 'spot');

        $this->applySorting(
            $query,
            $request->input('order_by', 'effective_date'),
            (int) $request->input('direction', -1)
        );

        return $this->paginateResource($query, $request, CarrierContractResource::class);
    }

    /**
     * GET /api/v1/carrier-contracts/custom
     */
    public function customContracts(Request $request): JsonResponse
    {
        $query = CarrierContract::where('contract_type', 'custom');

        $this->applySorting(
            $query,
            $request->input('order_by', 'effective_date'),
            (int) $request->input('direction', -1)
        );

        return $this->paginateResource($query, $request, CarrierContractResource::class);
    }

    /**
     * POST /api/v1/carrier-contracts
     */
    public function store(StoreCarrierContractRequest $request): JsonResponse
    {
        $contract = CarrierContract::create($request->validated());

        return $this->created(new CarrierContractResource($contract), 'Carrier contract created');
    }

    /**
     * PUT /api/v1/carrier-contracts/{uuid}
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        $contract = CarrierContract::where('uuid', $uuid)->first();

        if (! $contract) {
            return $this->notFound('Carrier contract not found');
        }

        $request->validate([
            'carrier_scac'           => 'sometimes|string|max:4',
            'contract_type'          => 'sometimes|in:spot,custom',
            'free_days_demurrage'    => 'sometimes|integer|min:0',
            'free_days_detention'    => 'nullable|integer|min:0',
            'demurrage_rates'        => 'sometimes|array',
            'detention_rates'        => 'nullable|array',
            'effective_date'         => 'sometimes|date',
            'expiry_date'            => 'nullable|date',
            'notes'                  => 'nullable|string',
            'is_active'              => 'nullable|boolean',
        ]);

        $contract->update($request->only([
            'carrier_scac', 'contract_type', 'free_days_demurrage', 'free_days_detention',
            'demurrage_rates', 'detention_rates', 'effective_date', 'expiry_date', 'notes', 'is_active',
        ]));

        return $this->success(new CarrierContractResource($contract->fresh()), 'Carrier contract updated');
    }

    /**
     * DELETE /api/v1/carrier-contracts/{uuid}
     */
    public function destroy(string $uuid): JsonResponse
    {
        $contract = CarrierContract::where('uuid', $uuid)->first();

        if (! $contract) {
            return $this->notFound('Carrier contract not found');
        }

        $contract->delete();

        return $this->noContent();
    }

    /**
     * GET /api/v1/carrier-contracts/carrier/{scac}
     */
    public function byCarrier(string $scac, Request $request): JsonResponse
    {
        $query = CarrierContract::where('carrier_scac', strtoupper($scac))
            ->where('is_active', true);

        $this->applySorting($query, 'effective_date', -1);

        return $this->paginateResource($query, $request, CarrierContractResource::class);
    }
}
