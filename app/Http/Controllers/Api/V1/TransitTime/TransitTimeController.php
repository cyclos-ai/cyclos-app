<?php

namespace App\Http\Controllers\Api\V1\TransitTime;

use App\Http\Controllers\Controller;
use App\Models\Tenant\TransitTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransitTimeController extends Controller
{
    /**
     * GET /api/v1/transit-times
     */
    public function index(Request $request): JsonResponse
    {
        $query = TransitTime::query();

        if ($request->input('carrier_scac')) {
            $query->where('carrier_scac', $request->input('carrier_scac'));
        }

        if ($request->input('pol')) {
            $query->where('pol', $request->input('pol'));
        }

        if ($request->input('pod')) {
            $query->where('pod', $request->input('pod'));
        }

        $this->applySorting(
            $query,
            $request->input('order_by', 'pol'),
            (int) $request->input('direction', 1)
        );

        return $this->paginate($query, $request);
    }

    /**
     * POST /api/v1/transit-times/filter
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

        $query = TransitTime::query();

        if ($request->has('filters')) {
            $this->applyFilters($query, $request->input('filters', []));
        }

        $this->applySorting($query, $request->input('order_by'), (int) $request->input('direction', 1));

        return $this->paginate($query, $request);
    }

    /**
     * GET /api/v1/transit-times/trends
     */
    public function trends(Request $request): JsonResponse
    {
        $pol          = $request->input('pol');
        $pod          = $request->input('pod');
        $carrierScac  = $request->input('carrier_scac');
        $months       = (int) $request->input('months', 6);

        $query = TransitTime::query()
            ->selectRaw('YEAR(departure_date) as year, MONTH(departure_date) as month, AVG(transit_days) as avg_transit_days, COUNT(*) as shipments')
            ->where('departure_date', '>=', now()->subMonths($months)->startOfMonth())
            ->groupByRaw('YEAR(departure_date), MONTH(departure_date)')
            ->orderByRaw('YEAR(departure_date), MONTH(departure_date)');

        if ($pol) $query->where('pol', $pol);
        if ($pod) $query->where('pod', $pod);
        if ($carrierScac) $query->where('carrier_scac', $carrierScac);

        $trends = $query->get()->map(function ($row) {
            return [
                'year'              => (int) $row->year,
                'month'             => (int) $row->month,
                'avg_transit_days'  => round((float) $row->avg_transit_days, 1),
                'shipments'         => (int) $row->shipments,
            ];
        });

        return $this->success([
            'filters' => compact('pol', 'pod', 'carrierScac', 'months'),
            'trends'  => $trends,
        ]);
    }
}
