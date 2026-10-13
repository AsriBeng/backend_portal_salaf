<?php

namespace App\Http\Resources;

use App\Enums\Role;
use App\Models\Santri;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Santri */
class SantriResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        // Data sensitif (NIK, KK, dokumen) hanya untuk admin, sekretaris, dan santri itu sendiri
        $lengkap = $user && ($user->hasRole(Role::Admin, Role::Sekretaris) || $user->id === $this->user_id);

        return [
            'id' => $this->id,
            'nis' => $this->nis,
            'nama_lengkap' => $this->nama_lengkap,
            'jenis_kelamin' => $this->jenis_kelamin->value,
            'jenis_kelamin_label' => $this->jenis_kelamin->label(),
            'tempat_lahir' => $this->tempat_lahir,
            'tanggal_lahir' => $this->tanggal_lahir?->toDateString(),
            'status' => $this->status->value,
            'tanggal_masuk' => $this->tanggal_masuk?->toDateString(),
            'tanggal_keluar' => $this->tanggal_keluar?->toDateString(),
            'kamar_id' => $this->kamar_id,
            'kamar' => new KamarResource($this->whenLoaded('kamar')),
            'foto_url' => $this->fotoUrl(),
            'is_active' => $this->whenLoaded('user', fn () => $this->user->is_active),

            $this->mergeWhen($lengkap, fn () => [
                'nik' => $this->nik,
                'no_kk' => $this->no_kk,
                'agama' => $this->agama,
                'kewarganegaraan' => $this->kewarganegaraan,
                'anak_ke' => $this->anak_ke,
                'jumlah_saudara' => $this->jumlah_saudara,
                'no_telepon' => $this->no_telepon,
                'alamat' => $this->alamat,
                'rt' => $this->rt,
                'rw' => $this->rw,
                'kelurahan' => $this->kelurahan,
                'kecamatan' => $this->kecamatan,
                'kabupaten' => $this->kabupaten,
                'provinsi' => $this->provinsi,
                'kode_pos' => $this->kode_pos,
                'nama_ayah' => $this->nama_ayah,
                'pekerjaan_ayah' => $this->pekerjaan_ayah,
                'no_telepon_ayah' => $this->no_telepon_ayah,
                'nama_ibu' => $this->nama_ibu,
                'pekerjaan_ibu' => $this->pekerjaan_ibu,
                'no_telepon_ibu' => $this->no_telepon_ibu,
                'nama_wali' => $this->nama_wali,
                'hubungan_wali' => $this->hubungan_wali,
                'no_telepon_wali' => $this->no_telepon_wali,
                'dokumen' => collect(['file_kk', 'file_akta', 'file_ktp'])->mapWithKeys(fn ($jenis) => [
                    $jenis => $this->{$jenis} ? route('santri.dokumen', [$this->id, $jenis]) : null,
                ]),
            ]),
        ];
    }
}
