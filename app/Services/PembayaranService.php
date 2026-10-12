<?php

namespace App\Services;

use App\Enums\KasTipe;
use App\Enums\PembayaranStatus;
use App\Models\KasTransaksi;
use App\Models\KategoriKas;
use App\Models\Pembayaran;
use App\Models\Tagihan;
use App\Models\User;
use App\Notifications\PembayaranDiverifikasi;
use App\Support\Rupiah;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PembayaranService
{
    /**
     * Santri mengunggah bukti (status menunggu) atau bendahara/admin mencatat pembayaran langsung (langsung disetujui).
     *
     * @param  array{nominal:int, metode:string, tanggal_bayar:string, catatan?:string|null}  $data
     */
    public function buat(Tagihan $tagihan, array $data, ?UploadedFile $bukti, User $oleh): Pembayaran
    {
        $pembayaran = DB::transaction(function () use ($tagihan, $data, $bukti, $oleh) {
            $tagihan = Tagihan::whereKey($tagihan->id)->lockForUpdate()->firstOrFail();

            $maks = $tagihan->sisaBisaDibayar();

            if ($data['nominal'] > $maks) {
                throw ValidationException::withMessages([
                    'nominal' => $maks === 0
                        ? 'Tagihan ini sudah lunas atau sedang menunggu verifikasi.'
                        : 'Nominal melebihi sisa tagihan ('.Rupiah::format($maks).').',
                ]);
            }

            $dariSantri = $oleh->isSantri();

            $pembayaran = $tagihan->pembayaran()->create([
                'nominal' => $data['nominal'],
                'metode' => $data['metode'],
                'sumber' => $dariSantri ? 'santri' : 'bendahara',
                'bukti' => $bukti?->store('bukti-pembayaran'),
                'status' => PembayaranStatus::Menunggu,
                'tanggal_bayar' => $data['tanggal_bayar'],
                'catatan' => $data['catatan'] ?? null,
                'created_by' => $oleh->id,
            ]);

            if (! $dariSantri) {
                $this->terapkan($pembayaran, $oleh);
            }

            return $pembayaran;
        });

        if ($pembayaran->status === PembayaranStatus::Disetujui) {
            $this->beritahuSantri($pembayaran);
        }

        return $pembayaran->refresh();
    }

    public function setujui(Pembayaran $pembayaran, User $oleh): Pembayaran
    {
        DB::transaction(function () use ($pembayaran, $oleh) {
            $pembayaran = Pembayaran::whereKey($pembayaran->id)->lockForUpdate()->firstOrFail();
            $this->pastikanMenunggu($pembayaran);
            $this->terapkan($pembayaran, $oleh);
        });

        $pembayaran->refresh();
        $this->beritahuSantri($pembayaran);

        return $pembayaran;
    }

    public function tolak(Pembayaran $pembayaran, string $alasan, User $oleh): Pembayaran
    {
        DB::transaction(function () use ($pembayaran, $alasan, $oleh) {
            $pembayaran = Pembayaran::whereKey($pembayaran->id)->lockForUpdate()->firstOrFail();
            $this->pastikanMenunggu($pembayaran);

            $pembayaran->update([
                'status' => PembayaranStatus::Ditolak,
                'alasan_tolak' => $alasan,
                'verified_by' => $oleh->id,
                'verified_at' => now(),
            ]);
        });

        $pembayaran->refresh();
        $this->beritahuSantri($pembayaran);

        return $pembayaran;
    }

    /** Tandai disetujui, tambah nilai terbayar tagihan, dan catat kas masuk. Dipanggil di dalam transaksi. */
    private function terapkan(Pembayaran $pembayaran, User $oleh): void
    {
        $tagihan = Tagihan::whereKey($pembayaran->tagihan_id)->lockForUpdate()->firstOrFail();

        if ($pembayaran->nominal > $tagihan->sisa()) {
            throw ValidationException::withMessages([
                'nominal' => 'Nominal melebihi sisa tagihan ('.Rupiah::format($tagihan->sisa()).').',
            ]);
        }

        $pembayaran->update([
            'status' => PembayaranStatus::Disetujui,
            'alasan_tolak' => null,
            'verified_by' => $oleh->id,
            'verified_at' => now(),
        ]);

        $tagihan->terbayar += $pembayaran->nominal;
        $tagihan->refreshStatus();
        $tagihan->save();

        KasTransaksi::create([
            'gender' => $tagihan->santri->jenis_kelamin,
            'tipe' => KasTipe::Masuk,
            'kategori_kas_id' => KategoriKas::pembayaranSantri()->id,
            'nominal' => $pembayaran->nominal,
            'tanggal' => $pembayaran->tanggal_bayar,
            'keterangan' => "{$tagihan->judul} - {$tagihan->santri->nama_lengkap} ({$tagihan->santri->nis})",
            'pembayaran_id' => $pembayaran->id,
            'created_by' => $oleh->id,
        ]);
    }

    private function pastikanMenunggu(Pembayaran $pembayaran): void
    {
        if ($pembayaran->status !== PembayaranStatus::Menunggu) {
            throw ValidationException::withMessages([
                'status' => 'Pembayaran ini sudah diverifikasi sebelumnya.',
            ]);
        }
    }

    private function beritahuSantri(Pembayaran $pembayaran): void
    {
        $pembayaran->tagihan->santri->user?->notify(new PembayaranDiverifikasi($pembayaran));
    }
}
