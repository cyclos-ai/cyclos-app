<?php

namespace App\Http\Resources\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'          => $this->uuid,
            'name'          => $this->name,
            'email'         => $this->email,
            'phone'         => $this->phone,
            'timezone'      => $this->timezone,
            'locale'        => $this->locale,
            'roles'         => $this->whenLoaded('roles', fn() => $this->roles->pluck('name')),
            'permissions'   => $this->whenLoaded('permissions', fn() => $this->permissions->pluck('name')),
            'notifications' => $this->notifications,
            'preferences'   => $this->preferences,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at'    => $this->created_at?->toIso8601String(),
            'updated_at'    => $this->updated_at?->toIso8601String(),
        ];
    }
}
