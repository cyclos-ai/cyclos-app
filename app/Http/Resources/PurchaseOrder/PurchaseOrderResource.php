<?php

namespace App\Http\Resources\PurchaseOrder;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'          => $this->uuid,
            'po_number'     => $this->po_number,
            'vendor_uuid'   => $this->vendor_uuid,
            'factory_uuid'  => $this->factory_uuid,
            'order_date'    => $this->order_date,
            'required_date' => $this->required_date,
            'ship_date'     => $this->ship_date,
            'status'        => $this->status,
            'currency'      => $this->currency ?? 'USD',
            'total_amount'  => $this->total_amount !== null ? (float) $this->total_amount : null,
            'notes'         => $this->notes,
            'created_at'    => $this->created_at?->toIso8601String(),
            'updated_at'    => $this->updated_at?->toIso8601String(),
            'items'         => $this->whenLoaded('items'),
        ];
    }
}
