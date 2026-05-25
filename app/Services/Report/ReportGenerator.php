<?php

namespace App\Services\Report;

use App\Models\Tenant\Container;
use App\Models\Tenant\DemurrageCharge;
use App\Models\Tenant\OceanInvoice;
use App\Models\Tenant\Report;
use App\Models\Tenant\TransitTime;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ReportGenerator
{
    public function generate(Report $report): array
    {
        return match ($report->type) {
            'container_status'    => $this->generateContainerStatusReport($report),
            'demurrage_summary'   => $this->generateDemurrageSummaryReport($report),
            'transit_time'        => $this->generateTransitTimeReport($report),
            'invoice_summary'     => $this->generateInvoiceSummaryReport($report),
            default               => throw new \InvalidArgumentException("Unknown report type: {$report->type}"),
        };
    }

    public function generateContainerStatusReport(Report $report): array
    {
        $query = Container::query();

        $this->applyFilters($query, $report->filters ?? []);

        $columns = $report->columns ?? ['id', 'container_number', 'status', 'eta', 'carrier_scac'];

        $containers = $query->get($columns);

        return [
            'type'    => 'container_status',
            'columns' => $columns,
            'rows'    => $containers->toArray(),
            'summary' => [
                'total'          => $containers->count(),
                'by_status'      => $containers->groupBy('status')->map->count()->toArray(),
            ],
        ];
    }

    public function generateDemurrageSummaryReport(Report $report): array
    {
        $query = DemurrageCharge::query()->with('container');

        $this->applyFilters($query, $report->filters ?? []);

        $charges = $query->get();

        return [
            'type'    => 'demurrage_summary',
            'columns' => ['container_id', 'free_days', 'days_accrued', 'estimated_cost', 'actual_cost', 'alarm_active'],
            'rows'    => $charges->toArray(),
            'summary' => [
                'total_charges'        => $charges->count(),
                'total_estimated_cost' => $charges->sum('estimated_cost'),
                'total_actual_cost'    => $charges->sum('actual_cost'),
                'active_alarms'        => $charges->where('alarm_active', true)->count(),
            ],
        ];
    }

    public function generateTransitTimeReport(Report $report): array
    {
        $query = TransitTime::query()->with('container');

        $this->applyFilters($query, $report->filters ?? []);

        $transitTimes = $query->get();

        return [
            'type'    => 'transit_time',
            'columns' => ['container_id', 'origin', 'destination', 'planned_days', 'actual_days', 'variance'],
            'rows'    => $transitTimes->toArray(),
            'summary' => [
                'total_records'       => $transitTimes->count(),
                'avg_planned_days'    => round($transitTimes->avg('planned_days'), 1),
                'avg_actual_days'     => round($transitTimes->avg('actual_days'), 1),
                'on_time_percentage'  => $this->calcOnTimePercentage($transitTimes),
            ],
        ];
    }

    public function generateInvoiceSummaryReport(Report $report): array
    {
        $query = OceanInvoice::query();

        $this->applyFilters($query, $report->filters ?? []);

        $invoices = $query->get();

        return [
            'type'    => 'invoice_summary',
            'columns' => ['invoice_number', 'status', 'invoice_date', 'due_date', 'total_amount'],
            'rows'    => $invoices->toArray(),
            'summary' => [
                'total_invoices'  => $invoices->count(),
                'total_amount'    => $invoices->sum('total_amount'),
                'by_status'       => $invoices->groupBy(fn($i) => $i->status->value)->map->count()->toArray(),
                'overdue_count'   => $invoices->filter(fn($i) => $i->due_date !== null && $i->due_date->isPast() && $i->status->value !== 'PAID')->count(),
            ],
        ];
    }

    public function exportToExcel(Report $report, array $data): string
    {
        // Stub: In production uses maatwebsite/excel or PhpSpreadsheet
        Log::info('ReportGenerator: exportToExcel stub called', ['report_id' => $report->id]);
        $path = storage_path("reports/report-{$report->id}.xlsx");
        return $path;
    }

    public function exportToCsv(Report $report, array $data): string
    {
        $path = storage_path("reports/report-{$report->id}.csv");

        $rows    = $data['rows'] ?? [];
        $columns = $data['columns'] ?? (empty($rows) ? [] : array_keys((array) $rows[0]));

        $handle = fopen($path, 'w');
        fputcsv($handle, $columns);

        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn($col) => $row[$col] ?? '', $columns));
        }

        fclose($handle);

        return $path;
    }

    public function exportToPdf(Report $report, array $data): string
    {
        // Stub: In production uses Barryvdh/Laravel-DomPDF
        Log::info('ReportGenerator: exportToPdf stub called', ['report_id' => $report->id]);
        $path = storage_path("reports/report-{$report->id}.pdf");
        return $path;
    }

    private function applyFilters($query, array $filters): void
    {
        foreach ($filters as $field => $value) {
            if (is_array($value)) {
                $query->whereIn($field, $value);
            } elseif (is_string($value) && str_contains($value, ',')) {
                $query->whereIn($field, explode(',', $value));
            } else {
                $query->where($field, $value);
            }
        }
    }

    private function calcOnTimePercentage(Collection $transitTimes): float
    {
        if ($transitTimes->isEmpty()) {
            return 0.0;
        }

        $onTime = $transitTimes->filter(
            fn($t) => $t->actual_days !== null && $t->actual_days <= $t->planned_days
        )->count();

        return round(($onTime / $transitTimes->count()) * 100, 1);
    }
}
