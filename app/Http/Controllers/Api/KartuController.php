<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Santri;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Data kartu santri untuk ditampilkan dan dicetak (PDF ukuran KTP) di aplikasi.
 * QR code dibuat di aplikasi dari "qr_url".
 */
class KartuController extends Controller
{
    /** Kartu milik santri yang login. */
    public function saya(Request $request): JsonResponse
    {
        $santri = $request->user()->santri;
        abort_unless($santri, 404, 'Data santri tidak ditemukan.');

        return $this->kartu($santri);
    }

    public function show(Request $request, Santri $santri): JsonResponse
    {
        $this->ensureGenderAccess($request->user(), $santri->jenis_kelamin);

        return $this->kartu($santri);
    }

    /** Buat kode QR baru, misalnya jika kartu hilang. Kartu lama otomatis tidak berlaku. */
    public function regenerate(Request $request, Santri $santri): JsonResponse
    {
        $this->ensureGenderAccess($request->user(), $santri->jenis_kelamin);

        $santri->update(['card_token' => Santri::newCardToken()]);

        return $this->kartu($santri);
    }

    private function kartu(Santri $santri): JsonResponse
    {
        $alamat = collect([
            $santri->alamat,
            $santri->rt || $santri->rw ? "RT {$santri->rt}/RW {$santri->rw}" : null,
            $santri->kelurahan,
            'Kec. '.$santri->kecamatan,
            $santri->kabupaten,
            $santri->provinsi,
        ])->filter()->implode(', ');

        return response()->json([
            'data' => [
                'pondok' => [
                    'nama' => Setting::value('nama_pondok'),
                    'alamat' => Setting::value('alamat_pondok'),
                    'logo_url' => url('/logo'),
                ],
                'santri' => [
                    'id' => $santri->id,
                    'nis' => $santri->nis,
                    'nama_lengkap' => $santri->nama_lengkap,
                    'jenis_kelamin' => $santri->jenis_kelamin->value,
                    'tempat_lahir' => $santri->tempat_lahir,
                    'tanggal_lahir' => $santri->tanggal_lahir->toDateString(),
                    'ttl' => $santri->tempat_lahir.', '.$santri->tanggal_lahir->translatedFormat('d F Y'),
                    'alamat' => $alamat,
                    'foto_url' => $santri->foto_profil ? route('santri.dokumen', [$santri->id, 'foto_profil']) : null,
                ],
                'status' => $santri->status->value,
                'berlaku' => $santri->isAktif() ? 'Selama aktif sebagai santri' : 'Tidak berlaku',
                'qr_url' => $santri->cardUrl(),
            ],
        ]);
    }
}
