<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            'gender' => $this->gender?->value,
            'gender_label' => $this->gender?->label(),
            'phone' => $this->phone,
            'foto_url' => $this->fotoUrl(),
            'is_active' => $this->is_active,
            'santri' => new SantriResource($this->whenLoaded('santri')),
            'created_at' => $this->created_at,
        ];
    }
}
