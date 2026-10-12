<?php

namespace App\Http\Controllers\Api;

use App\Enums\Gender;
use App\Enums\KasTipe;
use App\Enums\PembayaranStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\KasTransaksi;
use App\Models\Pembayaran;
use App\Models\Santri;
use App\Models\Tagihan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class LaporanController extends Controller
{
    /**
     * Rekap keuangan satu periode: tagihan terbit, pembayaran diterima, arus kas per kategori.
     * ?dari=YYYY-MM-DD&sampai=YYYY-MM-DD (bawaan: bulan ini) ?gender=L|P (admin)
     */
    public function keuangan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'dari' => ['nullable', 'date'],
            'sampai' => ['nullable', 'date', 'after_or_equal:dari'],
            'gender' => ['nullable', 'in:L,P'],
        ]);

        $user = $request->user();
        $dari = Carbon::parse($data['dari'] ?? now()->startOfMonth())->startOfDay();
        $sampai = Carbon::parse($data['sampai'] ?? now()->endOfMonth())->endOfDay();
        $gender = $user->genderScope()?->value ?? ($data['gender'] ?? null);

        $tagihan = Tagihan::visibleTo($user)
            ->when($gender, fn ($q) => $q->whereHas('santri', fn ($q) => $q->where('jenis_kelamin', $gender)))
            ->whereBetween('created_at', [$dari, $sampai])
            ->selectRaw('COUNT(*) AS jumlah, COALESCE(SUM(nominal), 0) AS total, COALESCE(SUM(terbayar), 0) AS terbayar')
            ->first();

        $pembayaran = Pembayaran::visibleTo($user)
            ->when($gender, fn ($q) => $q->whereHas('tagihan.santri', fn ($q) => $q->where('jenis_kelamin', $gender)))
            ->where('status', PembayaranStatus::Disetujui)
            ->whereBetween('tanggal_bayar', [$dari->toDateString(), $sampai->toDateString()])
            ->selectRaw('COUNT(*) AS jumlah, COALESCE(SUM(nominal), 0) AS total')
            ->first();

        $kas = KasTransaksi::visibleTo($user)
            ->gender($gender)
            ->whereBetween('tanggal', [$dari->toDateString(), $sampai->toDateString()])
            ->join('kategori_kas', 'kategori_kas.id', '=', 'kas_transaksi.kategori_kas_id')
            ->groupBy('kas_transaksi.tipe', 'kategori_kas.nama')
            ->selectRaw('kas_transaksi.tipe, kategori_kas.nama AS kategori, SUM(kas_transaksi.nominal) AS total')
            ->get();

        $masuk = (int) $kas->where('tipe', KasTipe::Masuk)->sum('total');
        $keluar = (int) $kas->where('tipe', KasTipe::Keluar)->sum('total');

        $saldoAkhir = collect($gender ? [Gender::from($gender)] : ($user->genderScope() ? [$user->genderScope()] : Gender::cases()))
            ->sum(fn (Gender $g) => KasTransaksi::saldo($g));

        return response()->json([
            'data' => [
                'periode' => ['dari' => $dari->toDateString(), 'sampai' => $sampai->toDateString()],
                'gender' => $gender,
                'tagihan_terbit' => [
                    'jumlah' => (int) $tagihan->jumlah,
                    'total' => (int) $tagihan->total,
                    'terbayar' => (int) $tagihan->terbayar,
                    'sisa' => (int) $tagihan->total - (int) $tagihan->terbayar,
                ],
                'pembayaran_diterima' => [
                    'jumlah' => (int) $pembayaran->jumlah,
                    'total' => (int) $pembayaran->total,
                ],
                'kas' => [
                    'masuk' => $masuk,
                    'keluar' => $keluar,
                    'selisih' => $masuk - $keluar,
                    'saldo_saat_ini' => $saldoAkhir,
                    'per_kategori' => $kas->map(fn ($row) => [
                        'tipe' => $row->tipe instanceof KasTipe ? $row->tipe->value : $row->tipe,
                        'kategori' => $row->kategori,
                        'total' => (int) $row->total,
                    ])->values(),
                ],
            ],
        ]);
    }

    /** Daftar santri yang masih punya tagihan belum lunas, urut tunggakan terbesar. ?gender= ?search= */
    public function tunggakan(Request $request): JsonResponse
    {
        $user = $request->user();

        $santri = Santri::visibleTo($user)
            ->gender($user->isAdmin() ? $request->query('gender') : null)
            ->when($request->query('search'), fn ($q, $s) => $q->where(fn ($q) => $q
                ->where('nama_lengkap', 'like', "%{$s}%")
                ->orWhere('nis', 'like', "%{$s}%")))
            ->whereHas('tagihan', fn ($q) => $q->belumLunas())
            ->withCount(['tagihan as jumlah_tagihan' => fn ($q) => $q->belumLunas()])
            ->withSum(['tagihan as total_nominal' => fn ($q) => $q->belumLunas()], 'nominal')
            ->withSum(['tagihan as total_terbayar' => fn ($q) => $q->belumLunas()], 'terbayar')
            ->orderByRaw('(COALESCE(total_nominal, 0) - COALESCE(total_terbayar, 0)) DESC')
            ->paginate($this->perPage($request));

        $santri->getCollection()->transform(fn (Santri $s) => [
            'id' => $s->id,
            'nis' => $s->nis,
            'nama_lengkap' => $s->nama_lengkap,
            'jenis_kelamin' => $s->jenis_kelamin->value,
            'status' => $s->status->value,
            'jumlah_tagihan' => (int) $s->jumlah_tagihan,
            'total_tunggakan' => (int) $s->total_nominal - (int) $s->total_terbayar,
        ]);

        return response()->json($santri);
    }

    /** Rekap jumlah santri per status dan per komplek (untuk sekretaris / ketua / admin). */
    public function santri(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user->hasRole(Role::Bendahara), 403);

        $base = Santri::visibleTo($user)->gender($user->isAdmin() ? $request->query('gender') : null);

        $perStatus = (clone $base)->selectRaw('status, jenis_kelamin, COUNT(*) AS jumlah')
            ->groupBy('status', 'jenis_kelamin')
            ->get()
            ->map(fn ($r) => ['status' => $r->status->value, 'jenis_kelamin' => $r->jenis_kelamin->value, 'jumlah' => (int) $r->jumlah]);

        $perKomplek = (clone $base)->aktif()
            ->join('kamar', 'kamar.id', '=', 'santri.kamar_id')
            ->join('komplek', 'komplek.id', '=', 'kamar.komplek_id')
            ->selectRaw('komplek.id, komplek.nama, komplek.gender, COUNT(*) AS jumlah')
            ->groupBy('komplek.id', 'komplek.nama', 'komplek.gender')
            ->orderBy('komplek.nama')
            ->get()
            ->map(fn ($r) => ['komplek_id' => $r->id, 'nama' => $r->nama, 'gender' => $r->gender, 'jumlah' => (int) $r->jumlah]);

        $tanpaKamar = (clone $base)->aktif()->whereNull('kamar_id')->count();

        return response()->json([
            'data' => [
                'per_status' => $perStatus,
                'per_komplek' => $perKomplek,
                'aktif_tanpa_kamar' => $tanpaKamar,
            ],
        ]);
    }
}
