<?php

namespace App\Http\Resources\Report;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'               => $this->uuid,
            'name'               => $this->name,
            'report_type'        => $this->report_type,
            'config'             => $this->config,
            'filters'            => $this->filters,
            'columns'            => $this->columns,
            'status'             => $this->status,
            'last_generated_at'  => $this->last_generated_at?->toIso8601String(),
            'download_url'       => $this->download_url,
            'user_id'            => $this->user_id,
            'schedules'          => $this->whenLoaded('schedules'),
            'created_at'         => $this->created_at?->toIso8601String(),
            'updated_at'         => $this->updated_at?->toIso8601String(),
        ];
    }
}
