<?php

namespace App\Http\Resources;

use App\Models\KeringananSpp;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin KeringananSpp */
class KeringananSppResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'santri_id' => $this->santri_id,
            'nominal' => $this->nominal,
            'mulai' => $this->mulai->format('Y-m'),
            'sampai' => $this->sampai?->format('Y-m'),
            'keterangan' => $this->keterangan,
            'berlaku' => $this->mulai->lte(now()) && ($this->sampai === null || $this->sampai->gte(now()->startOfMonth())),
        ];
    }
}
