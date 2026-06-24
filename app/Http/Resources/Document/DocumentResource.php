<?php

namespace App\Http\Resources\Document;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'           => $this->id,
            'original_name'  => $this->original_name,
            'document_type'  => $this->document_type,
            'mime_type'      => $this->mime_type,
            'size'           => (int) $this->size,
            'mbl_uuid'       => $this->mbl_id,
            'container_uuid' => $this->container_id,
            'booking_uuid'   => $this->booking_id,
            'download_url'   => "/api/v1/documents/{$this->id}/download",
            'uploaded_by'    => $this->uploaded_by,
            'created_at'     => $this->created_at?->toIso8601String(),
            'updated_at'     => $this->updated_at?->toIso8601String(),
        ];
    }
}
