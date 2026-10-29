<?php

namespace App\Services;

use App\Enums\Gender;
use App\Enums\JenisTagihanTipe;
use App\Enums\PembayaranStatus;
use App\Enums\TagihanStatus;
use App\Models\JenisTagihan;
use App\Models\Santri;
use App\Models\Setting;
use App\Models\Tagihan;
use App\Models\User;
use App\Notifications\TagihanBaru;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TagihanService
{
    /**
     * Buat tagihan SPP bulan tertentu untuk semua santri aktif.
     * Aman dijalankan berulang: tagihan yang sudah ada tidak dibuat dobel.
     * Santri dengan keringanan memakai nominal keringanannya; keringanan 0 = tidak ditagih.
     *
     * @return int jumlah tagihan baru
     */
    public function generateSpp(?CarbonInterface $bulan = null): int
    {
        $periode = Carbon::parse($bulan ?? now())->startOfMonth();
        $hari = min((int) Setting::value('spp_jatuh_tempo_hari') ?: 10, $periode->daysInMonth);
        $jatuhTempo = $periode->copy()->day($hari);
        $dibuat = 0;

        $jenisSpp = JenisTagihan::where('tipe', JenisTagihanTipe::Spp)->where('is_active', true)->where('nominal', '>', 0)->get();

        foreach ($jenisSpp as $jenis) {
            Santri::aktif()
                ->where('tanggal_masuk', '<=', $periode->copy()->endOfMonth())
                ->whereDoesntHave('tagihan', fn ($q) => $q
                    ->where('jenis_tagihan_id', $jenis->id)
                    ->whereDate('periode', $periode))
                ->with(['user', 'keringananSpp' => fn ($q) => $q->berlakuPada($periode)])
                ->chunkById(200, function ($daftarSantri) use ($jenis, $periode, $jatuhTempo, &$dibuat) {
                    foreach ($daftarSantri as $santri) {
                        $nominal = $santri->keringananSpp->first()->nominal ?? $jenis->nominal;
                        if ($nominal <= 0) {
                            continue;
                        }

                        $tagihan = Tagihan::create([
                            'santri_id' => $santri->id,
                            'jenis_tagihan_id' => $jenis->id,
                            'judul' => $jenis->nama.' '.$periode->translatedFormat('F Y'),
                            'periode' => $periode,
                            'nominal' => $nominal,
                            'jatuh_tempo' => $jatuhTempo,
                        ]);

                        $santri->user?->notify(new TagihanBaru($tagihan));
                        $dibuat++;
                    }
                });
        }

        return $dibuat;
    }

    /**
     * Samakan nominal tagihan SPP santri yang sudah terbit pada rentang bulan tertentu dengan
     * keringanan yang berlaku (atau nominal SPP normal jika tidak ada keringanan).
     * Tagihan lunas, yang masih punya pembayaran menunggu verifikasi, atau yang sudah dibayar
     * melebihi nominal baru dibiarkan. Nominal 0 menghapus tagihan yang belum pernah dibayar.
     *
     * @return int jumlah tagihan yang diubah atau dihapus
     */
    public function sesuaikanSpp(Santri $santri, CarbonInterface $dari, ?CarbonInterface $sampai): int
    {
        $daftarTagihan = $santri->tagihan()
            ->with('jenisTagihan')
            ->whereHas('jenisTagihan', fn ($q) => $q->where('tipe', JenisTagihanTipe::Spp))
            ->whereDate('periode', '>=', $dari)
            ->when($sampai, fn ($q) => $q->whereDate('periode', '<=', $sampai))
            ->where('status', '!=', TagihanStatus::Lunas)
            ->whereDoesntHave('pembayaran', fn ($q) => $q->where('status', PembayaranStatus::Menunggu))
            ->get();

        $diubah = 0;

        foreach ($daftarTagihan as $tagihan) {
            $nominal = $santri->keringananSpp()->berlakuPada($tagihan->periode)->value('nominal');
            $nominal = $nominal === null ? $tagihan->jenisTagihan->nominal : (int) $nominal;

            if ($nominal === $tagihan->nominal || $nominal < $tagihan->terbayar) {
                continue;
            }

            if ($nominal === 0) {
                // terbayar 0 dan tidak ada yang menunggu: sisa pembayarannya hanya yang ditolak
                DB::transaction(function () use ($tagihan) {
                    $tagihan->pembayaran()->delete();
                    $tagihan->delete();
                });
            } else {
                $tagihan->nominal = $nominal;
                $tagihan->refreshStatus();
                $tagihan->save();
            }

            $diubah++;
        }

        return $diubah;
    }

    /**
     * Buat tagihan iuran untuk sekelompok santri aktif.
     *
     * @param  array<int, int>|null  $santriIds  null = semua santri aktif sesuai gender
     * @return int jumlah tagihan dibuat
     */
    public function buatIuran(
        JenisTagihan $jenis,
        string $judul,
        int $nominal,
        ?Gender $gender,
        ?array $santriIds,
        ?CarbonInterface $jatuhTempo,
        User $pembuat,
    ): int {
        $query = Santri::aktif()
            ->when($gender, fn ($q) => $q->where('jenis_kelamin', $gender->value))
            ->when($santriIds !== null, fn ($q) => $q->whereIn('id', $santriIds))
            ->with('user');

        $tagihanBaru = DB::transaction(function () use ($query, $jenis, $judul, $nominal, $jatuhTempo, $pembuat) {
            return $query->get()->map(fn (Santri $santri) => Tagihan::create([
                'santri_id' => $santri->id,
                'jenis_tagihan_id' => $jenis->id,
                'judul' => $judul,
                'nominal' => $nominal,
                'jatuh_tempo' => $jatuhTempo,
                'created_by' => $pembuat->id,
            ]));
        });

        foreach ($tagihanBaru as $tagihan) {
            $tagihan->santri->user?->notify(new TagihanBaru($tagihan));
        }

        return $tagihanBaru->count();
    }
}
