<?php

namespace App\Http\Controllers\Api;

use App\Enums\JenisTagihanTipe;
use App\Http\Controllers\Controller;
use App\Http\Resources\JenisTagihanResource;
use App\Models\JenisTagihan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class JenisTagihanController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $jenis = JenisTagihan::query()
            ->when($request->query('tipe'), fn ($q, $t) => $q->where('tipe', $t))
            ->when($request->has('aktif'), fn ($q) => $q->where('is_active', $request->boolean('aktif')))
            ->orderBy('tipe')
            ->orderBy('nama')
            ->get();

        return JenisTagihanResource::collection($jenis);
    }

    public function store(Request $request): JsonResponse
    {
        $jenis = JenisTagihan::create($request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'tipe' => ['required', Rule::enum(JenisTagihanTipe::class)],
            'nominal' => ['required', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]));

        return (new JenisTagihanResource($jenis))->response()->setStatusCode(201);
    }

    /** Perubahan nominal SPP berlaku untuk tagihan bulan berikutnya; tagihan yang sudah terbit tidak berubah. */
    public function update(Request $request, JenisTagihan $jenisTagihan): JenisTagihanResource
    {
        $jenisTagihan->update($request->validate([
            'nama' => ['sometimes', 'string', 'max:255'],
            'nominal' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]));

        return new JenisTagihanResource($jenisTagihan);
    }

    public function destroy(JenisTagihan $jenisTagihan): JsonResponse
    {
        if ($jenisTagihan->tagihan()->exists()) {
            $jenisTagihan->update(['is_active' => false]);

            return response()->json(['message' => 'Jenis tagihan sudah dipakai, sehingga hanya dinonaktifkan.']);
        }

        $jenisTagihan->delete();

        return response()->json(['message' => 'Jenis tagihan dihapus.']);
    }
}
