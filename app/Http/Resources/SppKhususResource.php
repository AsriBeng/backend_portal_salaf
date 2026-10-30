<?php

namespace App\Http\Resources;

use App\Models\SppKhusus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SppKhusus */
class SppKhususResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nama' => $this->nama,
            'nominal' => $this->nominal,
            'mulai' => $this->mulai->format('Y-m'),
            'sampai' => $this->sampai?->format('Y-m'),
            'keterangan' => $this->keterangan,
            'berlaku' => $this->berlaku(),
            // santri di luar wewenang pengguna (gender lain) tidak ditampilkan dan membuat SPP khusus ini tidak bisa diubah
            'bisa_kelola' => (int) $this->santri_tersembunyi_count === 0,
            'santri' => $this->whenLoaded('santri', fn () => $this->santri->map(fn ($s) => [
                'id' => $s->id,
                'nis' => $s->nis,
                'nama_lengkap' => $s->nama_lengkap,
                'jenis_kelamin' => $s->jenis_kelamin->value,
            ])),
        ];
    }
}
