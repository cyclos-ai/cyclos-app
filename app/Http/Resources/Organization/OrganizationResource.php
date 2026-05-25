<?php

namespace App\Http\Resources\Organization;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'          => $this->uuid,
            'name'          => $this->name,
            'website'       => $this->website,
            'phone'         => $this->phone,
            'address'       => $this->address,
            'city'          => $this->city,
            'state'         => $this->state,
            'country_code'  => $this->country_code,
            'zip_code'      => $this->zip_code,
            'timezone'      => $this->timezone,
            'locale'        => $this->locale,
            'logo_url'      => $this->logo_url,
            'sso_enabled'   => (bool) $this->sso_enabled,
            'sso_provider'  => $this->sso_provider,
            'sso_domain'    => $this->sso_domain,
            'member_count'  => $this->whenCounted('users'),
            'settings'      => $this->settings,
            'created_at'    => $this->created_at?->toIso8601String(),
            'updated_at'    => $this->updated_at?->toIso8601String(),
        ];
    }
}
