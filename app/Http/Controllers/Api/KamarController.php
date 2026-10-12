<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\KamarResource;
use App\Http\Resources\SantriResource;
use App\Models\Kamar;
use App\Models\Komplek;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class KamarController extends Controller
{
    /** Daftar kamar; ?komplek_id= dan ?tersedia=1 (hanya kamar yang masih ada tempat). */
    public function index(Request $request): AnonymousResourceCollection
    {
        $kamar = Kamar::visibleTo($request->user())
            ->when($request->query('komplek_id'), fn ($q, $id) => $q->where('komplek_id', $id))
            ->when($request->query('gender'), fn ($q, $g) => $q->whereHas('komplek', fn ($q) => $q->where('gender', $g)))
            ->with('komplek')
            ->withCount('santriAktif')
            ->orderBy('komplek_id')
            ->orderBy('nama')
            ->get()
            ->when($request->boolean('tersedia'), fn ($c) => $c->filter(fn (Kamar $k) => $k->santri_aktif_count < $k->kapasitas)->values());

        return KamarResource::collection($kamar);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'komplek_id' => ['required', 'exists:komplek,id'],
            'nama' => ['required', 'string', 'max:255'],
            'kapasitas' => ['required', 'integer', 'min:1', 'max:1000'],
        ]);

        $komplek = Komplek::findOrFail($data['komplek_id']);
        $this->ensureGenderAccess($request->user(), $komplek->gender);

        if ($komplek->kamar()->where('nama', $data['nama'])->exists()) {
            throw ValidationException::withMessages(['nama' => 'Nama kamar sudah dipakai di komplek ini.']);
        }

        $kamar = Kamar::create($data)->loadCount('santriAktif');

        return (new KamarResource($kamar))->response()->setStatusCode(201);
    }

    public function show(Request $request, Kamar $kamar): JsonResponse
    {
        $this->ensureGenderAccess($request->user(), $kamar->komplek->gender);

        $kamar->load('komplek')->loadCount('santriAktif');

        return response()->json([
            'data' => new KamarResource($kamar),
            'santri' => SantriResource::collection($kamar->santriAktif()->orderBy('nama_lengkap')->get()),
        ]);
    }

    public function update(Request $request, Kamar $kamar): KamarResource
    {
        $this->ensureGenderAccess($request->user(), $kamar->komplek->gender);

        $data = $request->validate([
            'nama' => ['sometimes', 'string', 'max:255'],
            'kapasitas' => ['sometimes', 'integer', 'min:1', 'max:1000'],
        ]);

        $terisi = $kamar->santriAktif()->count();

        if (isset($data['kapasitas']) && $data['kapasitas'] < $terisi) {
            throw ValidationException::withMessages([
                'kapasitas' => "Kapasitas tidak boleh kurang dari jumlah penghuni saat ini ({$terisi} santri).",
            ]);
        }

        $kamar->update($data);

        return new KamarResource($kamar->loadCount('santriAktif'));
    }

    public function destroy(Request $request, Kamar $kamar): JsonResponse
    {
        $this->ensureGenderAccess($request->user(), $kamar->komplek->gender);
        abort_if($kamar->santriAktif()->exists(), 422, 'Kamar masih dihuni santri. Pindahkan santri terlebih dahulu.');

        $kamar->delete();

        return response()->json(['message' => 'Kamar dihapus.']);
    }
}
