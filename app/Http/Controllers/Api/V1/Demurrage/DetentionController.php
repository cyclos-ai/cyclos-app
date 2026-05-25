<?php

namespace App\Http\Controllers\Api\V1\Demurrage;

use App\Http\Controllers\Controller;
use App\Http\Resources\Demurrage\DemurrageChargeResource;
use App\Models\Tenant\DetentionCharge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DetentionController extends Controller
{
    /**
     * GET /api/v1/detention
     */
    public function index(Request $request): JsonResponse
    {
        $query = DetentionCharge::query();

        if ($request->has('filters')) {
            $this->applyFilters($query, $request->input('filters', []));
        }

        $this->applySorting(
            $query,
            $request->input('order_by', 'last_free_day'),
            (int) $request->input('direction', 1)
        );

        return $this->paginate($query, $request);
    }

    /**
     * GET /api/v1/detention/{uuid}
     */
    public function show(string $uuid): JsonResponse
    {
        $charge = DetentionCharge::where('uuid', $uuid)->first();

        if (! $charge) {
            return $this->notFound('Detention charge not found');
        }

        return $this->success($charge);
    }

    /**
     * POST /api/v1/detention/calculate
     */
    public function calculate(Request $request): JsonResponse
    {
        $request->validate([
            'outgate_date'      => 'required|date',
            'empty_return_date' => 'nullable|date',
            'free_days'         => 'required|integer|min:0',
            'daily_rate'        => 'required|numeric|min:0',
            'currency'          => 'nullable|string|max:3',
        ]);

        $outgateDate     = \Carbon\Carbon::parse($request->input('outgate_date'));
        $emptyReturnDate = $request->input('empty_return_date')
            ? \Carbon\Carbon::parse($request->input('empty_return_date'))
            : now();
        $freeDays    = (int) $request->input('free_days');
        $dailyRate   = (float) $request->input('daily_rate');

        $daysUsed     = $outgateDate->diffInDays($emptyReturnDate);
        $billableDays = max(0, $daysUsed - $freeDays);
        $totalCharge  = $billableDays * $dailyRate;

        return $this->success([
            'outgate_date'      => $outgateDate->toDateString(),
            'empty_return_date' => $emptyReturnDate->toDateString(),
            'free_days'         => $freeDays,
            'days_used'         => $daysUsed,
            'billable_days'     => $billableDays,
            'daily_rate'        => $dailyRate,
            'total_charge'      => round($totalCharge, 2),
            'currency'          => $request->input('currency', 'USD'),
        ]);
    }

    /**
     * GET /api/v1/detention/container/{uuid}
     */
    public function byContainer(string $containerUuid, Request $request): JsonResponse
    {
        $query = DetentionCharge::where('container_uuid', $containerUuid);

        $this->applySorting($query, $request->input('order_by', 'last_free_day'), (int) $request->input('direction', 1));

        return $this->paginate($query, $request);
    }

    /**
     * GET /api/v1/detention/alarms
     */
    public function alarms(Request $request): JsonResponse
    {
        $daysThreshold = (int) $request->input('days_threshold', 2);

        $query = DetentionCharge::query()
            ->where('last_free_day', '<=', now()->addDays($daysThreshold))
            ->where('last_free_day', '>=', now())
            ->whereNull('empty_return_date');

        $this->applySorting($query, 'last_free_day', 1);

        return $this->paginate($query, $request);
    }
}
