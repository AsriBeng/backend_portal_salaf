<?php

namespace App\Http\Controllers\Api;

use App\Enums\Gender;
use App\Enums\JenisTagihanTipe;
use App\Enums\PembayaranStatus;
use App\Enums\TagihanStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\TagihanResource;
use App\Models\JenisTagihan;
use App\Models\Santri;
use App\Models\Tagihan;
use App\Services\TagihanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TagihanController extends Controller
{
    /**
     * Santri: tagihan miliknya. Pengurus: tagihan santri sesuai gender.
     * Filter: ?status= ?santri_id= ?jenis_tagihan_id= ?periode=YYYY-MM ?belum_lunas=1 ?search=
     */
    public function index(Request $request): JsonResponse
    {
        $query = Tagihan::visibleTo($request->user())
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->boolean('belum_lunas'), fn ($q) => $q->belumLunas())
            ->when($request->query('santri_id'), fn ($q, $id) => $q->where('santri_id', $id))
            ->when($request->query('jenis_tagihan_id'), fn ($q, $id) => $q->where('jenis_tagihan_id', $id))
            ->when($request->query('periode'), fn ($q, $p) => $q->whereDate('periode', Carbon::parse($p.'-01')))
            ->when($request->query('gender'), fn ($q, $g) => $q->whereHas('santri', fn ($q) => $q->where('jenis_kelamin', $g)))
            ->when($request->query('search'), fn ($q, $s) => $q->where(fn ($q) => $q
                ->where('judul', 'like', "%{$s}%")
                ->orWhereHas('santri', fn ($q) => $q->where('nama_lengkap', 'like', "%{$s}%")->orWhere('nis', 'like', "%{$s}%"))));

        // Ringkasan dihitung dari filter yang sama, sebelum paginasi
        $ringkasan = (clone $query)->selectRaw('COUNT(*) AS jumlah, COALESCE(SUM(nominal), 0) AS total, COALESCE(SUM(terbayar), 0) AS terbayar')->first();

        $tagihan = $query->with('santri', 'jenisTagihan')
            ->orderByRaw("FIELD(status, 'belum_bayar', 'dicicil', 'lunas')")
            ->latest('id')
            ->paginate($this->perPage($request));

        return TagihanResource::collection($tagihan)->additional([
            'ringkasan' => [
                'jumlah' => (int) $ringkasan->jumlah,
                'total' => (int) $ringkasan->total,
                'terbayar' => (int) $ringkasan->terbayar,
                'sisa' => (int) $ringkasan->total - (int) $ringkasan->terbayar,
            ],
        ])->response();
    }

    public function show(Request $request, Tagihan $tagihan): TagihanResource
    {
        $this->pastikanAkses($request, $tagihan);

        return new TagihanResource($tagihan->load(['santri', 'jenisTagihan', 'pembayaran' => fn ($q) => $q->latest()]));
    }

    /**
     * Buat tagihan iuran. Tujuan: semua santri aktif (sesuai gender) atau santri tertentu (santri_ids).
     * Admin memilih gender lewat "gender" (L/P) atau kosong untuk putra + putri.
     */
    public function store(Request $request, TagihanService $service): JsonResponse
    {
        $data = $request->validate([
            'jenis_tagihan_id' => ['required', Rule::exists('jenis_tagihan', 'id')->where('is_active', true)],
            'judul' => ['nullable', 'string', 'max:255'],
            'nominal' => ['nullable', 'integer', 'min:1'],
            'jatuh_tempo' => ['nullable', 'date'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'santri_ids' => ['nullable', 'array', 'min:1'],
            'santri_ids.*' => ['integer', 'exists:santri,id'],
        ]);

        $jenis = JenisTagihan::findOrFail($data['jenis_tagihan_id']);

        if ($jenis->tipe !== JenisTagihanTipe::Iuran) {
            throw ValidationException::withMessages([
                'jenis_tagihan_id' => 'Tagihan SPP dibuat otomatis setiap bulan. Pilih jenis iuran.',
            ]);
        }

        $user = $request->user();
        $gender = $user->genderScope() ?? (isset($data['gender']) ? Gender::from($data['gender']) : null);

        if (isset($data['santri_ids'])) {
            $ids = array_unique($data['santri_ids']);
            $bisaDiakses = Santri::visibleTo($user)->whereIn('id', $ids)->count();
            abort_if($bisaDiakses !== count($ids), 403, 'Ada santri di luar wilayah akses Anda.');
        }

        $jumlah = $service->buatIuran(
            $jenis,
            $data['judul'] ?? $jenis->nama,
            $data['nominal'] ?? $jenis->nominal,
            $gender,
            $data['santri_ids'] ?? null,
            isset($data['jatuh_tempo']) ? Carbon::parse($data['jatuh_tempo']) : null,
            $user,
        );

        return response()->json(['message' => "{$jumlah} tagihan berhasil dibuat.", 'jumlah' => $jumlah], 201);
    }

    /** Ubah judul / nominal / jatuh tempo selama belum ada pembayaran yang disetujui. */
    public function update(Request $request, Tagihan $tagihan): TagihanResource
    {
        $this->pastikanAkses($request, $tagihan);

        $data = $request->validate([
            'judul' => ['sometimes', 'string', 'max:255'],
            'nominal' => ['sometimes', 'integer', 'min:1'],
            'jatuh_tempo' => ['nullable', 'date'],
        ]);

        if (isset($data['nominal']) && $data['nominal'] < $tagihan->terbayar) {
            throw ValidationException::withMessages([
                'nominal' => 'Nominal tidak boleh lebih kecil dari jumlah yang sudah dibayar.',
            ]);
        }

        $tagihan->fill($data);
        $tagihan->refreshStatus();
        $tagihan->save();

        return new TagihanResource($tagihan->load('santri', 'jenisTagihan'));
    }

    public function destroy(Request $request, Tagihan $tagihan): JsonResponse
    {
        $this->pastikanAkses($request, $tagihan);

        abort_if(
            $tagihan->status !== TagihanStatus::BelumBayar || $tagihan->pembayaran()->where('status', '!=', PembayaranStatus::Ditolak)->exists(),
            422,
            'Tagihan yang sudah memiliki pembayaran tidak dapat dihapus.',
        );

        $tagihan->pembayaran()->delete();
        $tagihan->delete();

        return response()->json(['message' => 'Tagihan dihapus.']);
    }

    /** Jalankan pembuatan SPP secara manual (aman diulang, tidak membuat dobel). ?periode=YYYY-MM */
    public function generateSpp(Request $request, TagihanService $service): JsonResponse
    {
        $data = $request->validate(['periode' => ['nullable', 'date_format:Y-m']]);
        $periode = isset($data['periode']) ? Carbon::parse($data['periode'].'-01') : now();

        $jumlah = $service->generateSpp($periode);

        return response()->json([
            'message' => $jumlah > 0
                ? "{$jumlah} tagihan SPP {$periode->translatedFormat('F Y')} berhasil dibuat."
                : "Semua santri aktif sudah memiliki tagihan SPP {$periode->translatedFormat('F Y')}.",
            'jumlah' => $jumlah,
        ]);
    }

    private function pastikanAkses(Request $request, Tagihan $tagihan): void
    {
        abort_unless(Tagihan::whereKey($tagihan->id)->visibleTo($request->user())->exists(), 404);
    }
}
