<?php

namespace App\Http\Resources;

use App\Models\Tagihan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Tagihan */
class TagihanResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'judul' => $this->judul,
            'periode' => $this->periode?->format('Y-m'),
            'nominal' => $this->nominal,
            'terbayar' => $this->terbayar,
            'sisa' => $this->sisa(),
            'status' => $this->status->value,
            'jatuh_tempo' => $this->jatuh_tempo?->toDateString(),
            'jenis_tagihan' => new JenisTagihanResource($this->whenLoaded('jenisTagihan')),
            'santri' => $this->whenLoaded('santri', fn () => [
                'id' => $this->santri->id,
                'nis' => $this->santri->nis,
                'nama_lengkap' => $this->santri->nama_lengkap,
                'jenis_kelamin' => $this->santri->jenis_kelamin->value,
            ]),
            'pembayaran' => PembayaranResource::collection($this->whenLoaded('pembayaran')),
            'created_at' => $this->created_at,
        ];
    }
}
