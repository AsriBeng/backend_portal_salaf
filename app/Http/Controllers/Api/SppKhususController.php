<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SppKhususResource;
use App\Models\Santri;
use App\Models\SppKhusus;
use App\Models\User;
use App\Services\TagihanService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * SPP khusus (potongan / bebas SPP) untuk sekelompok santri pada rentang bulan tertentu.
 * Tagihan SPP yang sudah terbit dan belum dibayar milik santri yang terdampak ikut disesuaikan.
 */
class SppKhususController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $daftar = $this->query($request->user())->orderByDesc('mulai')->orderBy('nama')->get();

        return SppKhususResource::collection($daftar);
    }

    public function store(Request $request, TagihanService $service): JsonResponse
    {
        $data = $this->validasi($request);

        $sppKhusus = DB::transaction(function () use ($request, $data) {
            $sppKhusus = SppKhusus::create([...$data['atribut'], 'created_by' => $request->user()->id]);
            $sppKhusus->santri()->sync($data['santri_ids']);

            return $sppKhusus;
        });

        $diubah = $this->sesuaikan($service, $data['santri_ids'], $sppKhusus->mulai, $sppKhusus->sampai);

        return $this->respon($request, $sppKhusus, 'SPP khusus disimpan.', $diubah)->setStatusCode(201);
    }

    public function update(Request $request, SppKhusus $sppKhusus, TagihanService $service): JsonResponse
    {
        $this->pastikanBisaKelola($request, $sppKhusus);
        $data = $this->validasi($request, $sppKhusus->id);

        // santri lama & baru, rentang lama & baru, sama-sama disesuaikan
        $santriIds = $sppKhusus->santri()->pluck('santri.id')->merge($data['santri_ids'])->unique()->all();
        $dari = $sppKhusus->mulai->min($data['atribut']['mulai']);
        $sampai = $sppKhusus->sampai === null || $data['atribut']['sampai'] === null
            ? null
            : $sppKhusus->sampai->max($data['atribut']['sampai']);

        DB::transaction(function () use ($sppKhusus, $data) {
            $sppKhusus->update($data['atribut']);
            $sppKhusus->santri()->sync($data['santri_ids']);
        });

        $diubah = $this->sesuaikan($service, $santriIds, $dari, $sampai);

        return $this->respon($request, $sppKhusus, 'SPP khusus diperbarui.', $diubah);
    }

    public function destroy(Request $request, SppKhusus $sppKhusus, TagihanService $service): JsonResponse
    {
        $this->pastikanBisaKelola($request, $sppKhusus);
        $santriIds = $sppKhusus->santri()->pluck('santri.id')->all();

        $sppKhusus->delete();
        $diubah = $this->sesuaikan($service, $santriIds, $sppKhusus->mulai, $sppKhusus->sampai);

        return response()->json(['message' => $this->pesan('SPP khusus dihapus.', $diubah), 'tagihan_disesuaikan' => $diubah]);
    }

    /** @return array{atribut: array{nama: string, nominal: int, mulai: Carbon, sampai: ?Carbon, keterangan: ?string}, santri_ids: array<int, int>} */
    private function validasi(Request $request, ?int $kecuali = null): array
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'nominal' => ['required', 'integer', 'min:0'],
            'mulai' => ['required', 'date_format:Y-m'],
            'sampai' => ['nullable', 'date_format:Y-m', 'after_or_equal:mulai'],
            'keterangan' => ['nullable', 'string', 'max:255'],
            'santri_ids' => ['required', 'array', 'min:1'],
            'santri_ids.*' => ['integer', 'distinct'],
        ], [
            'santri_ids.required' => 'Pilih minimal satu santri.',
            'santri_ids.min' => 'Pilih minimal satu santri.',
        ]);

        $mulai = Carbon::parse($data['mulai'].'-01');
        $sampai = isset($data['sampai']) ? Carbon::parse($data['sampai'].'-01') : null;

        $santriIds = Santri::whereKey($data['santri_ids'])->visibleTo($request->user())->pluck('id')->all();
        if (count($santriIds) !== count($data['santri_ids'])) {
            throw ValidationException::withMessages(['santri_ids' => 'Ada santri yang tidak ditemukan.']);
        }

        // satu santri hanya boleh masuk satu SPP khusus pada bulan yang sama
        $bentrok = SppKhusus::bertumpuk($mulai, $sampai)
            ->when($kecuali, fn ($q) => $q->whereKeyNot($kecuali))
            ->whereHas('santri', fn ($q) => $q->whereKey($santriIds))
            ->with(['santri' => fn ($q) => $q->whereKey($santriIds)])
            ->first();

        if ($bentrok) {
            $nama = $bentrok->santri->pluck('nama_lengkap')->take(3)->implode(', ');
            throw ValidationException::withMessages([
                'santri_ids' => "{$nama} sudah masuk SPP khusus \"{$bentrok->nama}\" pada bulan yang bertumpuk.",
            ]);
        }

        return [
            'atribut' => [
                'nama' => $data['nama'],
                'nominal' => $data['nominal'],
                'mulai' => $mulai,
                'sampai' => $sampai,
                'keterangan' => $data['keterangan'] ?? null,
            ],
            'santri_ids' => $santriIds,
        ];
    }

    /** @param  array<int, int>  $santriIds */
    private function sesuaikan(TagihanService $service, array $santriIds, Carbon $dari, ?Carbon $sampai): int
    {
        return Santri::whereKey($santriIds)->get()->sum(fn (Santri $s) => $service->sesuaikanSpp($s, $dari, $sampai));
    }

    /** SPP khusus beserta santri yang boleh dilihat pengguna dan jumlah santri di luar wewenangnya. */
    private function query(User $user): Builder
    {
        return SppKhusus::query()
            ->with(['santri' => fn ($q) => $q->visibleTo($user)->orderBy('nama_lengkap')])
            ->withCount(['santri as santri_tersembunyi_count' => fn ($q) => $q->whereNotIn('santri.id', Santri::visibleTo($user)->select('id'))]);
    }

    private function pastikanBisaKelola(Request $request, SppKhusus $sppKhusus): void
    {
        $tersembunyi = $sppKhusus->santri()->whereNotIn('santri.id', Santri::visibleTo($request->user())->select('id'))->exists();
        abort_if($tersembunyi, 403, 'SPP khusus ini juga berisi santri di luar wewenang Anda.');
    }

    private function respon(Request $request, SppKhusus $sppKhusus, string $pesan, int $diubah): JsonResponse
    {
        return (new SppKhususResource($this->query($request->user())->findOrFail($sppKhusus->id)))->additional([
            'message' => $this->pesan($pesan, $diubah),
            'tagihan_disesuaikan' => $diubah,
        ])->response();
    }

    private function pesan(string $pesan, int $diubah): string
    {
        return $diubah > 0 ? "{$pesan} {$diubah} tagihan SPP yang sudah terbit ikut disesuaikan." : $pesan;
    }
}
