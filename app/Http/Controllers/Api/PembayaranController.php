<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PembayaranResource;
use App\Models\Pembayaran;
use App\Models\Tagihan;
use App\Services\PembayaranService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PembayaranController extends Controller
{
    /** Filter: ?status=menunggu|disetujui|ditolak ?tagihan_id= ?santri_id= ?sumber= ?dari= ?sampai= */
    public function index(Request $request): AnonymousResourceCollection
    {
        $pembayaran = Pembayaran::visibleTo($request->user())
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('sumber'), fn ($q, $s) => $q->where('sumber', $s))
            ->when($request->query('tagihan_id'), fn ($q, $id) => $q->where('tagihan_id', $id))
            ->when($request->query('santri_id'), fn ($q, $id) => $q->whereHas('tagihan', fn ($q) => $q->where('santri_id', $id)))
            ->when($request->query('gender'), fn ($q, $g) => $q->whereHas('tagihan.santri', fn ($q) => $q->where('jenis_kelamin', $g)))
            ->when($request->query('dari'), fn ($q, $d) => $q->whereDate('tanggal_bayar', '>=', $d))
            ->when($request->query('sampai'), fn ($q, $d) => $q->whereDate('tanggal_bayar', '<=', $d))
            ->with('tagihan.santri', 'pembuat', 'verifikator')
            // yang menunggu verifikasi tampil paling atas
            ->orderByRaw("status = 'menunggu' DESC")
            ->latest('id')
            ->paginate($this->perPage($request));

        return PembayaranResource::collection($pembayaran);
    }

    /**
     * Santri: unggah bukti transfer (bukti wajib) → menunggu verifikasi bendahara.
     * Admin/bendahara: catat pembayaran langsung (tunai / transfer ke rekening bendahara) → langsung disetujui.
     */
    public function store(Request $request, PembayaranService $service): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'tagihan_id' => ['required', 'exists:tagihan,id'],
            'nominal' => ['required', 'integer', 'min:1'],
            'metode' => [$user->isSantri() ? 'nullable' : 'required', 'in:transfer,tunai'],
            'tanggal_bayar' => ['nullable', 'date', 'before_or_equal:today'],
            'catatan' => ['nullable', 'string', 'max:500'],
            'bukti' => [$user->isSantri() ? 'required' : 'nullable', 'image', 'max:4096'],
        ]);

        $tagihan = Tagihan::visibleTo($user)->findOrFail($data['tagihan_id']);

        $pembayaran = $service->buat($tagihan, [
            'nominal' => $data['nominal'],
            'metode' => $user->isSantri() ? 'transfer' : $data['metode'],
            'tanggal_bayar' => $data['tanggal_bayar'] ?? now()->toDateString(),
            'catatan' => $data['catatan'] ?? null,
        ], $request->file('bukti'), $user);

        return (new PembayaranResource($pembayaran->load('tagihan')))
            ->additional(['message' => $user->isSantri()
                ? 'Bukti pembayaran terkirim dan menunggu verifikasi bendahara.'
                : 'Pembayaran berhasil dicatat.'])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Pembayaran $pembayaran): PembayaranResource
    {
        $this->pastikanAkses($request, $pembayaran);

        return new PembayaranResource($pembayaran->load('tagihan.santri', 'pembuat', 'verifikator'));
    }

    public function setujui(Request $request, Pembayaran $pembayaran, PembayaranService $service): PembayaranResource
    {
        $this->pastikanAkses($request, $pembayaran);

        return (new PembayaranResource($service->setujui($pembayaran, $request->user())->load('tagihan')))
            ->additional(['message' => 'Pembayaran disetujui.']);
    }

    public function tolak(Request $request, Pembayaran $pembayaran, PembayaranService $service): PembayaranResource
    {
        $this->pastikanAkses($request, $pembayaran);

        $alasan = $request->validate(['alasan' => ['required', 'string', 'max:500']])['alasan'];

        return (new PembayaranResource($service->tolak($pembayaran, $alasan, $request->user())->load('tagihan')))
            ->additional(['message' => 'Pembayaran ditolak.']);
    }

    public function bukti(Request $request, Pembayaran $pembayaran): StreamedResponse
    {
        $this->pastikanAkses($request, $pembayaran);
        abort_unless($pembayaran->bukti && Storage::exists($pembayaran->bukti), 404, 'Bukti tidak tersedia.');

        return Storage::response($pembayaran->bukti);
    }

    private function pastikanAkses(Request $request, Pembayaran $pembayaran): void
    {
        abort_unless(Pembayaran::whereKey($pembayaran->id)->visibleTo($request->user())->exists(), 404);
    }
}
