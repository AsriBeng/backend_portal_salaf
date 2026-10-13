<?php

namespace App\Http\Resources;

use App\Models\Pengumuman;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Pengumuman */
class PengumumanResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'judul' => $this->judul,
            'isi' => $this->isi,
            'target' => $this->target->value,
            'lampiran_url' => $this->lampiran ? route('pengumuman.lampiran', $this->id) : null,
            'pembuat' => $this->whenLoaded('pembuat', fn () => [
                'id' => $this->pembuat->id,
                'name' => $this->pembuat->name,
                'role' => $this->pembuat->role->label(),
                'foto_url' => $this->pembuat->fotoUrl(),
            ]),
            'published_at' => $this->published_at,
        ];
    }
}
