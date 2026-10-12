<?php

namespace App\Http\Controllers\Api;

use App\Enums\Gender;
use App\Enums\KasTipe;
use App\Http\Controllers\Controller;
use App\Http\Resources\KasTransaksiResource;
use App\Models\KasTransaksi;
use App\Models\KategoriKas;
use App\Support\Rupiah;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KasController extends Controller
{
    /** Saldo kas putra, putri, dan total (admin). Pengurus lain hanya kas sesuai gendernya. */
    public function saldo(Request $request): JsonResponse
    {
        $scope = $request->user()->genderScope();
        $genders = $scope ? [$scope] : Gender::cases();

        $saldo = collect($genders)->mapWithKeys(fn (Gender $g) => [
            $g->target() => KasTransaksi::saldo($g),
        ]);

        return response()->json([
            'data' => [
                ...$saldo,
                'total' => $saldo->sum(),
            ],
        ]);
    }

    /** Filter: ?tipe=masuk|keluar ?kategori_kas_id= ?dari= ?sampai= ?gender= (admin) ?search= */
    public function index(Request $request): AnonymousResourceCollection
    {
        $transaksi = KasTransaksi::visibleTo($request->user())
            ->gender($request->query('gender'))
            ->when($request->query('tipe'), fn ($q, $t) => $q->where('tipe', $t))
            ->when($request->query('kategori_kas_id'), fn ($q, $id) => $q->where('kategori_kas_id', $id))
            ->when($request->query('dari'), fn ($q, $d) => $q->whereDate('tanggal', '>=', $d))
            ->when($request->query('sampai'), fn ($q, $d) => $q->whereDate('tanggal', '<=', $d))
            ->when($request->query('search'), fn ($q, $s) => $q->where('keterangan', 'like', "%{$s}%"))
            ->with('kategori', 'pembuat')
            ->latest('tanggal')
            ->latest('id')
            ->paginate($this->perPage($request));

        return KasTransaksiResource::collection($transaksi);
    }

    /**
     * Catat pengeluaran (memotong kas) atau pemasukan lain di luar pembayaran santri.
     * Admin memilih kas putra / putri lewat "gender"; bendahara otomatis kas gendernya.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tipe' => ['required', Rule::enum(KasTipe::class)],
            'kategori_kas_id' => ['required', 'exists:kategori_kas,id'],
            'nominal' => ['required', 'integer', 'min:1'],
            'tanggal' => ['nullable', 'date', 'before_or_equal:today'],
            'keterangan' => ['required', 'string', 'max:1000'],
            'bukti' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
        ]);

        $gender = $this->resolveGender($request);
        $kategori = KategoriKas::findOrFail($data['kategori_kas_id']);

        if ($kategori->tipe->value !== $data['tipe'] || $kategori->isSistem()) {
            throw ValidationException::withMessages(['kategori_kas_id' => 'Kategori tidak sesuai dengan jenis transaksi.']);
        }

        $transaksi = DB::transaction(function () use ($data, $gender, $request) {
            // Kunci baris kas gender ini supaya dua pengeluaran bersamaan tidak membuat saldo minus
            KasTransaksi::where('gender', $gender->value)->lockForUpdate()->count();

            if ($data['tipe'] === KasTipe::Keluar->value && $data['nominal'] > ($saldo = KasTransaksi::saldo($gender))) {
                throw ValidationException::withMessages([
                    'nominal' => "Saldo kas {$gender->label()} tidak mencukupi (saldo ".Rupiah::format($saldo).').',
                ]);
            }

            return KasTransaksi::create([
                ...$data,
                'gender' => $gender,
                'tanggal' => $data['tanggal'] ?? now()->toDateString(),
                'bukti' => $request->file('bukti')?->store('kas'),
                'created_by' => $request->user()->id,
            ]);
        });

        return (new KasTransaksiResource($transaksi->load('kategori', 'pembuat')))->response()->setStatusCode(201);
    }

    public function show(Request $request, KasTransaksi $kas): KasTransaksiResource
    {
        $this->ensureGenderAccess($request->user(), $kas->gender);

        return new KasTransaksiResource($kas->load('kategori', 'pembuat'));
    }

    /** Hapus transaksi manual (salah input). Kas dari pembayaran santri tidak bisa dihapus di sini. */
    public function destroy(Request $request, KasTransaksi $kas): JsonResponse
    {
        $this->ensureGenderAccess($request->user(), $kas->gender);
        abort_if($kas->pembayaran_id !== null, 422, 'Kas dari pembayaran santri tidak dapat dihapus.');

        if ($kas->tipe === KasTipe::Masuk && KasTransaksi::saldo($kas->gender) < $kas->nominal) {
            abort(422, 'Pemasukan ini tidak dapat dihapus karena saldo kas akan menjadi minus.');
        }

        if ($kas->bukti) {
            Storage::delete($kas->bukti);
        }

        $kas->delete();

        return response()->json(['message' => 'Transaksi kas dihapus.']);
    }

    public function bukti(Request $request, KasTransaksi $kas): StreamedResponse
    {
        $this->ensureGenderAccess($request->user(), $kas->gender);
        abort_unless($kas->bukti && Storage::exists($kas->bukti), 404, 'Bukti tidak tersedia.');

        return Storage::response($kas->bukti);
    }
}
