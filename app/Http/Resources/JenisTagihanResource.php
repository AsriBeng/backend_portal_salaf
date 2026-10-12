<?php

namespace App\Http\Resources;

use App\Models\JenisTagihan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin JenisTagihan */
class JenisTagihanResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nama' => $this->nama,
            'tipe' => $this->tipe->value,
            'nominal' => $this->nominal,
            'is_active' => $this->is_active,
        ];
    }
}
