<?php

namespace App\Http\Resources;

use App\Models\Pembayaran;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Pembayaran */
class PembayaranResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tagihan_id' => $this->tagihan_id,
            'nominal' => $this->nominal,
            'metode' => $this->metode,
            'sumber' => $this->sumber,
            'status' => $this->status->value,
            'alasan_tolak' => $this->alasan_tolak,
            'tanggal_bayar' => $this->tanggal_bayar?->toDateString(),
            'catatan' => $this->catatan,
            'bukti_url' => $this->bukti ? route('pembayaran.bukti', $this->id) : null,
            'tagihan' => new TagihanResource($this->whenLoaded('tagihan')),
            'pembuat' => $this->whenLoaded('pembuat', fn () => $this->pembuat->name),
            'verifikator' => $this->whenLoaded('verifikator', fn () => $this->verifikator?->name),
            'verified_at' => $this->verified_at,
            'created_at' => $this->created_at,
        ];
    }
}
