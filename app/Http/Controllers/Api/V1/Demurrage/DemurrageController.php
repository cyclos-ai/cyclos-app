<?php

namespace App\Http\Controllers\Api\V1\Demurrage;

use App\Http\Controllers\Controller;
use App\Http\Requests\Demurrage\CalculateDemurrageRequest;
use App\Http\Resources\Demurrage\DemurrageChargeResource;
use App\Models\Tenant\DemurrageCharge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DemurrageController extends Controller
{
    /**
     * GET /api/v1/demurrage
     */
    public function index(Request $request): JsonResponse
    {
        $query = DemurrageCharge::query();

        if ($request->has('filters')) {
            $this->applyFilters($query, $request->input('filters', []));
        }

        $this->applySorting(
            $query,
            $request->input('order_by', 'last_free_day'),
            (int) $request->input('direction', 1)
        );

        return $this->paginateResource($query, $request, DemurrageChargeResource::class);
    }

    /**
     * GET /api/v1/demurrage/{uuid}
     */
    public function show(string $uuid): JsonResponse
    {
        $charge = DemurrageCharge::where('uuid', $uuid)->first();

        if (! $charge) {
            return $this->notFound('Demurrage charge not found');
        }

        return $this->success(new DemurrageChargeResource($charge));
    }

    /**
     * POST /api/v1/demurrage/calculate
     */
    public function calculate(CalculateDemurrageRequest $request): JsonResponse
    {
        $data = $request->validated();

        $dischargeDate  = \Carbon\Carbon::parse($data['discharge_date']);
        $outgateDate    = isset($data['outgate_date']) ? \Carbon\Carbon::parse($data['outgate_date']) : now();
        $freeDays       = (int) ($data['free_days'] ?? 5);
        $dailyRate      = (float) ($data['daily_rate'] ?? 0);

        $daysUsed     = $dischargeDate->diffInDays($outgateDate);
        $billableDays = max(0, $daysUsed - $freeDays);
        $totalCharge  = $billableDays * $dailyRate;

        return $this->success([
            'discharge_date'  => $dischargeDate->toDateString(),
            'outgate_date'    => $outgateDate->toDateString(),
            'free_days'       => $freeDays,
            'days_used'       => $daysUsed,
            'billable_days'   => $billableDays,
            'daily_rate'      => $dailyRate,
            'total_charge'    => round($totalCharge, 2),
            'currency'        => $data['currency'] ?? 'USD',
        ]);
    }

    /**
     * GET /api/v1/demurrage/container/{uuid}
     */
    public function byContainer(string $containerUuid, Request $request): JsonResponse
    {
        $query = DemurrageCharge::where('container_uuid', $containerUuid);

        $this->applySorting($query, $request->input('order_by', 'last_free_day'), (int) $request->input('direction', 1));

        return $this->paginateResource($query, $request, DemurrageChargeResource::class);
    }

    /**
     * GET /api/v1/demurrage/alarms
     */
    public function alarms(Request $request): JsonResponse
    {
        $daysThreshold = (int) $request->input('days_threshold', 2);

        $query = DemurrageCharge::query()
            ->where('last_free_day', '<=', now()->addDays($daysThreshold))
            ->where('last_free_day', '>=', now())
            ->whereNull('outgate_date');

        $this->applySorting($query, 'last_free_day', 1);

        return $this->paginateResource($query, $request, DemurrageChargeResource::class);
    }

    /**
     * POST /api/v1/demurrage/filter
     */
    public function filter(Request $request): JsonResponse
    {
        $request->validate([
            'filters'            => 'nullable|array',
            'filters.*.field'    => 'required_with:filters|string',
            'filters.*.operator' => 'required_with:filters|string|in:eq,neq,gt,gte,lt,lte,contains,not_contains,starts_with,ends_with,is_null,is_not_null,in,not_in',
            'filters.*.value'    => 'nullable',
            'order_by'           => 'nullable|string',
            'direction'          => 'nullable|in:1,-1',
            'page_num'           => 'nullable|integer|min:0',
            'page_size'          => 'nullable|integer|min:1|max:50',
        ]);

        $query = DemurrageCharge::query();

        if ($request->has('filters')) {
            $this->applyFilters($query, $request->input('filters', []));
        }

        $this->applySorting($query, $request->input('order_by'), (int) $request->input('direction', 1));

        return $this->paginateResource($query, $request, DemurrageChargeResource::class);
    }
}
