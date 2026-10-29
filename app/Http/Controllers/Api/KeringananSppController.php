<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\KeringananSppResource;
use App\Models\KeringananSpp;
use App\Models\Santri;
use App\Services\TagihanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/** Keringanan SPP per santri. Tagihan SPP yang sudah terbit dan belum dibayar ikut disesuaikan. */
class KeringananSppController extends Controller
{
    public function index(Request $request, Santri $santri): AnonymousResourceCollection
    {
        $this->pastikanAkses($request, $santri);

        return KeringananSppResource::collection($santri->keringananSpp()->latest('mulai')->get());
    }

    public function store(Request $request, Santri $santri, TagihanService $service): JsonResponse
    {
        $this->pastikanAkses($request, $santri);
        $data = $this->validasi($request, $santri);

        $keringanan = $santri->keringananSpp()->create([...$data, 'created_by' => $request->user()->id]);
        $diubah = $service->sesuaikanSpp($santri, $keringanan->mulai, $keringanan->sampai);

        return $this->respon($keringanan, 'Keringanan SPP disimpan.', $diubah)->setStatusCode(201);
    }

    public function update(Request $request, KeringananSpp $keringananSpp, TagihanService $service): JsonResponse
    {
        $santri = $keringananSpp->santri;
        $this->pastikanAkses($request, $santri);
        $data = $this->validasi($request, $santri, $keringananSpp->id);

        // rentang lama dan baru sama-sama disesuaikan
        $dari = $keringananSpp->mulai->min($data['mulai']);
        $sampai = $keringananSpp->sampai === null || $data['sampai'] === null ? null : $keringananSpp->sampai->max($data['sampai']);

        $keringananSpp->update($data);
        $diubah = $service->sesuaikanSpp($santri, $dari, $sampai);

        return $this->respon($keringananSpp, 'Keringanan SPP diperbarui.', $diubah);
    }

    public function destroy(Request $request, KeringananSpp $keringananSpp, TagihanService $service): JsonResponse
    {
        $santri = $keringananSpp->santri;
        $this->pastikanAkses($request, $santri);

        $keringananSpp->delete();
        $diubah = $service->sesuaikanSpp($santri, $keringananSpp->mulai, $keringananSpp->sampai);

        return response()->json(['message' => $this->pesan('Keringanan SPP dihapus.', $diubah), 'tagihan_disesuaikan' => $diubah]);
    }

    /** @return array{nominal: int, mulai: Carbon, sampai: ?Carbon, keterangan: ?string} */
    private function validasi(Request $request, Santri $santri, ?int $kecuali = null): array
    {
        $data = $request->validate([
            'nominal' => ['required', 'integer', 'min:0'],
            'mulai' => ['required', 'date_format:Y-m'],
            'sampai' => ['nullable', 'date_format:Y-m', 'after_or_equal:mulai'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ]);

        $data['mulai'] = Carbon::parse($data['mulai'].'-01');
        $data['sampai'] = isset($data['sampai']) ? Carbon::parse($data['sampai'].'-01') : null;
        $data['keterangan'] ??= null;

        $bertumpuk = KeringananSpp::bertumpuk($santri->id, $data['mulai'], $data['sampai'])
            ->when($kecuali, fn ($q) => $q->whereKeyNot($kecuali))
            ->exists();

        if ($bertumpuk) {
            throw ValidationException::withMessages([
                'mulai' => 'Rentang bulan bertumpuk dengan keringanan lain milik santri ini.',
            ]);
        }

        return $data;
    }

    private function respon(KeringananSpp $keringanan, string $pesan, int $diubah): JsonResponse
    {
        return (new KeringananSppResource($keringanan))->additional([
            'message' => $this->pesan($pesan, $diubah),
            'tagihan_disesuaikan' => $diubah,
        ])->response();
    }

    private function pesan(string $pesan, int $diubah): string
    {
        return $diubah > 0 ? "{$pesan} {$diubah} tagihan SPP yang sudah terbit ikut disesuaikan." : $pesan;
    }

    private function pastikanAkses(Request $request, Santri $santri): void
    {
        abort_unless(Santri::whereKey($santri->id)->visibleTo($request->user())->exists(), 404);
    }
}
