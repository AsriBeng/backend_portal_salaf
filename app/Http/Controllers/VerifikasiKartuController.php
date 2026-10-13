<?php

namespace App\Http\Controllers;

use App\Models\Santri;
use App\Models\Setting;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Halaman publik yang dibuka saat QR kartu santri dipindai.
 * Hanya menampilkan data seperlunya (tanpa NIK, KK, atau nomor telepon).
 */
class VerifikasiKartuController extends Controller
{
    public function show(string $token): View
    {
        // Kartu terdaftar = asli, apa pun statusnya (aktif / lulus / keluar).
        // Data santri yang sudah dihapus dari sistem dianggap tidak terdaftar.
        $santri = Santri::with('kamar.komplek')->where('card_token', $token)->first();

        return view('kartu.verifikasi', [
            'santri' => $santri,
            'pondok' => Setting::values(),
            'token' => $token,
        ]);
    }

    public function foto(string $token): StreamedResponse
    {
        $santri = Santri::where('card_token', $token)->firstOrFail();
        abort_unless($santri->foto_profil && Storage::exists($santri->foto_profil), 404);

        return Storage::response($santri->foto_profil);
    }

    public function beranda(): View
    {
        return view('beranda', ['pondok' => Setting::values()]);
    }

    /** Logo yang diunggah admin; jika belum ada, pakai logo bawaan di folder public. */
    public function logo(): StreamedResponse|BinaryFileResponse
    {
        $logo = Setting::value('logo');
        $cache = ['Cache-Control' => 'public, max-age=86400'];

        if ($logo && Storage::exists($logo)) {
            return Storage::response($logo, null, $cache);
        }

        return response()->file(public_path('logo-pondok-512.png'), $cache);
    }
}
