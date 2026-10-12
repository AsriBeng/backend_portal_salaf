<?php

namespace App\Http\Resources;

use App\Models\Kamar;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Kamar */
class KamarResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'komplek_id' => $this->komplek_id,
            'nama' => $this->nama,
            'kapasitas' => $this->kapasitas,
            'terisi' => $this->whenCounted('santriAktif'),
            'sisa' => $this->when(isset($this->santri_aktif_count), fn () => $this->kapasitas - $this->santri_aktif_count),
            'komplek' => new KomplekResource($this->whenLoaded('komplek')),
        ];
    }
}
