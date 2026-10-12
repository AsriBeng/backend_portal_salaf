<?php

namespace App\Http\Controllers\Api;

use App\Enums\Gender;
use App\Enums\PembayaranStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Resources\PengumumanResource;
use App\Http\Resources\TagihanResource;
use App\Models\Kamar;
use App\Models\KasTransaksi;
use App\Models\Pembayaran;
use App\Models\Pengumuman;
use App\Models\Santri;
use App\Models\Tagihan;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Ringkasan beranda sesuai peran pengguna. */
class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $user->isSantri() ? $this->santri($user) : $this->pengurus($user);

        $data['pengumuman_terbaru'] = PengumumanResource::collection(
            Pengumuman::visibleTo($user)->where('published_at', '<=', now())->with('pembuat')->latest('published_at')->limit(3)->get()
        );
        $data['notifikasi_belum_dibaca'] = $user->unreadNotifications()->count();

        return response()->json(['data' => $data]);
    }

    /** @return array<string, mixed> */
    private function santri(User $user): array
    {
        $santri = $user->santri()->with('kamar.komplek')->first();
        abort_unless($santri, 404, 'Data santri tidak ditemukan.');

        $belumLunas = $santri->tagihan()->belumLunas()->with('jenisTagihan')->orderBy('periode')->orderBy('id')->get();

        return [
            'santri' => [
                'nis' => $santri->nis,
                'nama_lengkap' => $santri->nama_lengkap,
                'status' => $santri->status->value,
                'kamar' => $santri->kamar?->nama,
                'komplek' => $santri->kamar?->komplek?->nama,
                'foto_url' => $santri->foto_profil ? route('santri.dokumen', [$santri->id, 'foto_profil']) : null,
            ],
            'tagihan' => [
                'jumlah_belum_lunas' => $belumLunas->count(),
                'total_belum_lunas' => $belumLunas->sum(fn (Tagihan $t) => $t->sisa()),
                'daftar' => TagihanResource::collection($belumLunas->take(5)),
            ],
            'pembayaran_menunggu' => Pembayaran::visibleTo($user)->where('status', PembayaranStatus::Menunggu)->count(),
        ];
    }

    /** @return array<string, mixed> */
    private function pengurus(User $user): array
    {
        $scope = $user->genderScope();
        $genders = $scope ? [$scope] : Gender::cases();

        $santriAktif = Santri::visibleTo($user)->aktif();

        $data = [
            'cakupan' => $scope?->label() ?? 'Putra & Putri',
            'santri' => [
                'aktif' => (clone $santriAktif)->count(),
                'putra' => $scope === Gender::Putri ? null : (clone $santriAktif)->where('jenis_kelamin', Gender::Putra)->count(),
                'putri' => $scope === Gender::Putra ? null : (clone $santriAktif)->where('jenis_kelamin', Gender::Putri)->count(),
                'tanpa_kamar' => (clone $santriAktif)->whereNull('kamar_id')->count(),
            ],
        ];

        if ($user->hasRole(Role::Admin, Role::Ketua, Role::Sekretaris)) {
            $kamar = Kamar::visibleTo($user)->withCount('santriAktif')->get();
            $data['kamar'] = [
                'jumlah' => $kamar->count(),
                'kapasitas' => $kamar->sum('kapasitas'),
                'terisi' => $kamar->sum('santri_aktif_count'),
                'penuh' => $kamar->filter(fn (Kamar $k) => $k->santri_aktif_count >= $k->kapasitas)->count(),
            ];
        }

        if ($user->hasRole(Role::Admin, Role::Ketua, Role::Bendahara)) {
            $tagihan = Tagihan::visibleTo($user)->belumLunas()
                ->selectRaw('COUNT(*) AS jumlah, COALESCE(SUM(nominal - terbayar), 0) AS sisa')
                ->first();

            $saldo = collect($genders)->mapWithKeys(fn (Gender $g) => [$g->target() => KasTransaksi::saldo($g)]);

            $data['keuangan'] = [
                'tagihan_belum_lunas' => (int) $tagihan->jumlah,
                'total_tunggakan' => (int) $tagihan->sisa,
                'pembayaran_menunggu' => Pembayaran::visibleTo($user)->where('status', PembayaranStatus::Menunggu)->count(),
                'pembayaran_bulan_ini' => (int) Pembayaran::visibleTo($user)
                    ->where('status', PembayaranStatus::Disetujui)
                    ->whereBetween('tanggal_bayar', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
                    ->sum('nominal'),
                'saldo_kas' => [...$saldo, 'total' => $saldo->sum()],
            ];
        }

        return $data;
    }
}
