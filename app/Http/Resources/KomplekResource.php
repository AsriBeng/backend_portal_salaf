<?php

namespace App\Http\Resources;

use App\Models\Komplek;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Komplek */
class KomplekResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nama' => $this->nama,
            'gender' => $this->gender->value,
            'gender_label' => $this->gender->label(),
            'keterangan' => $this->keterangan,
            'jumlah_kamar' => $this->whenCounted('kamar'),
            'total_kapasitas' => $this->whenAggregated('kamar', 'kapasitas', 'sum', fn ($v) => (int) $v),
            'kamar' => KamarResource::collection($this->whenLoaded('kamar')),
        ];
    }
}
