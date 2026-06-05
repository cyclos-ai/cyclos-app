<?php

namespace App\Exports;

use App\Models\Tenant\ScheduledDrop;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ScheduledDropsExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private readonly Collection $drops) {}

    public function collection(): Collection
    {
        return $this->drops;
    }

    public function headings(): array
    {
        return [
            'Drayage_Carrier',
            'DC Code',
            'DC Name',
            'Vessel Eta',
            'Mother Vessel',
            'Estimated Drop Date',
            'Container_Number',
            'Terminal Pick Up',
            'Ocean SCAC',
            'Dem LFD',
            'Container Type',
            'Requested Stack',
            'Dray Notes',
        ];
    }

    /** @param ScheduledDrop $row */
    public function map($row): array
    {
        return [
            $row->drayage_carrier_name,
            $row->dc_code,
            $row->dc_name,
            $row->vessel_eta?->format('Y-m-d'),
            $row->mother_vessel,
            $row->estimated_drop_date?->format('Y-m-d'),
            $row->container_number,
            $row->terminal_pickup,
            $row->ocean_scac,
            $row->dem_lfd?->format('Y-m-d'),
            $row->container_type,
            $row->requested_stack,
            $row->dray_notes,
        ];
    }
}
