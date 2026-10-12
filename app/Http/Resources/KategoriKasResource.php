<?php

namespace App\Http\Resources;

use App\Models\KategoriKas;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin KategoriKas */
class KategoriKasResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nama' => $this->nama,
            'tipe' => $this->tipe->value,
            'sistem' => $this->isSistem(),
        ];
    }
}
