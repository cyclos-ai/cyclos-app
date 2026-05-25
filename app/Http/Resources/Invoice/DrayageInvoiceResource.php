<?php

namespace App\Http\Resources\Invoice;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DrayageInvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'                   => $this->uuid,
            'invoice_number'         => $this->invoice_number,
            'carrier_name'           => $this->carrier_name,
            'invoice_date'           => $this->invoice_date,
            'due_date'               => $this->due_date,
            'paid_date'              => $this->paid_date,
            'total_amount'           => (float) $this->total_amount,
            'paid_amount'            => (float) ($this->payments_sum_amount ?? 0),
            'currency'               => $this->currency ?? 'USD',
            'status'                 => $this->status,
            'container_uuid'         => $this->container_uuid,
            'import_drayage_uuid'    => $this->import_drayage_uuid,
            'created_at'             => $this->created_at?->toIso8601String(),
            'updated_at'             => $this->updated_at?->toIso8601String(),
            'items'                  => $this->whenLoaded('items'),
            'payments'               => $this->whenLoaded('payments'),
        ];
    }
}
