<?php

namespace App\Http\Resources;

use App\Models\KasTransaksi;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin KasTransaksi */
class KasTransaksiResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'gender' => $this->gender->value,
            'gender_label' => $this->gender->label(),
            'tipe' => $this->tipe->value,
            'nominal' => $this->nominal,
            'tanggal' => $this->tanggal?->toDateString(),
            'keterangan' => $this->keterangan,
            'kategori' => new KategoriKasResource($this->whenLoaded('kategori')),
            'pembayaran_id' => $this->pembayaran_id,
            'bukti_url' => $this->bukti ? route('kas.bukti', $this->id) : null,
            'pembuat' => $this->whenLoaded('pembuat', fn () => $this->pembuat->name),
            'created_at' => $this->created_at,
        ];
    }
}
