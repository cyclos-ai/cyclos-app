<?php

namespace App\Http\Controllers\Api\V1\Volume;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Container;
use App\Models\Tenant\OceanInvoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VolumeController extends Controller
{
    /**
     * GET /api/v1/volume/customer
     */
    public function customerVolume(Request $request): JsonResponse
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to   = $request->input('to', now()->toDateString());

        $volume = Container::query()
            ->selectRaw('COUNT(*) as total_containers')
            ->selectRaw('COUNT(DISTINCT carrier_scac) as carriers_used')
            ->whereBetween('created_at', [$from, $to])
            ->first();

        return $this->success([
            'period'            => ['from' => $from, 'to' => $to],
            'total_containers'  => (int) $volume->total_containers,
            'carriers_used'     => (int) $volume->carriers_used,
        ]);
    }

    /**
     * GET /api/v1/volume/billed
     */
    public function billedContainers(Request $request): JsonResponse
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to   = $request->input('to', now()->toDateString());

        $query = Container::query()
            ->whereHas('oceanInvoices', function ($q) use ($from, $to) {
                $q->whereBetween('invoice_date', [$from, $to]);
            });

        $this->applySorting($query, $request->input('order_by', 'container_number'), 1);

        return $this->paginate($query, $request);
    }

    /**
     * POST /api/v1/volume/savings
     */
    public function savingsCalculator(Request $request): JsonResponse
    {
        $request->validate([
            'containers_per_month'   => 'required|integer|min:1',
            'avg_demurrage_days'     => 'required|numeric|min:0',
            'avg_demurrage_rate'     => 'required|numeric|min:0',
            'avg_detention_days'     => 'nullable|numeric|min:0',
            'avg_detention_rate'     => 'nullable|numeric|min:0',
            'current_free_days'      => 'required|integer|min:0',
            'negotiated_free_days'   => 'required|integer|min:0',
        ]);

        $containersPerMonth = (int)   $request->input('containers_per_month');
        $avgDemurrageDays   = (float) $request->input('avg_demurrage_days');
        $avgDemurrageRate   = (float) $request->input('avg_demurrage_rate');
        $avgDetentionDays   = (float) $request->input('avg_detention_days', 0);
        $avgDetentionRate   = (float) $request->input('avg_detention_rate', 0);
        $currentFreeDays    = (int)   $request->input('current_free_days');
        $negotiatedFreeDays = (int)   $request->input('negotiated_free_days');

        $freeDaysDifference  = max(0, $negotiatedFreeDays - $currentFreeDays);
        $demurrageSavings    = $containersPerMonth * min($avgDemurrageDays, $freeDaysDifference) * $avgDemurrageRate;
        $detentionSavings    = $containersPerMonth * min($avgDetentionDays, $freeDaysDifference) * $avgDetentionRate;
        $totalMonthlySavings = $demurrageSavings + $detentionSavings;

        return $this->success([
            'monthly_containers'      => $containersPerMonth,
            'free_days_gained'        => $freeDaysDifference,
            'monthly_demurrage_savings' => round($demurrageSavings, 2),
            'monthly_detention_savings' => round($detentionSavings, 2),
            'total_monthly_savings'   => round($totalMonthlySavings, 2),
            'annual_savings'          => round($totalMonthlySavings * 12, 2),
            'currency'                => 'USD',
        ]);
    }

    /**
     * GET /api/v1/volume/summary
     */
    public function summary(Request $request): JsonResponse
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to   = $request->input('to', now()->toDateString());

        $totalContainers = Container::whereBetween('created_at', [$from, $to])->count();
        $activeContainers = Container::whereNotNull('vessel_uuid')
            ->where('status', '!=', 'delivered')
            ->count();

        $totalInvoiced = OceanInvoice::whereBetween('invoice_date', [$from, $to])
            ->sum('total_amount');

        return $this->success([
            'period'            => ['from' => $from, 'to' => $to],
            'total_containers'  => $totalContainers,
            'active_containers' => $activeContainers,
            'total_invoiced'    => round((float) $totalInvoiced, 2),
            'currency'          => 'USD',
        ]);
    }
}
