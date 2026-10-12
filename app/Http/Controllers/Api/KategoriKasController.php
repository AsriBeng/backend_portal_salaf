<?php

namespace App\Http\Controllers\Api;

use App\Enums\KasTipe;
use App\Http\Controllers\Controller;
use App\Http\Resources\KategoriKasResource;
use App\Models\KategoriKas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class KategoriKasController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $kategori = KategoriKas::query()
            ->when($request->query('tipe'), fn ($q, $t) => $q->where('tipe', $t))
            ->orderBy('tipe')
            ->orderBy('nama')
            ->get();

        return KategoriKasResource::collection($kategori);
    }

    public function store(Request $request): JsonResponse
    {
        $kategori = KategoriKas::create($request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'tipe' => ['required', Rule::enum(KasTipe::class)],
        ]));

        return (new KategoriKasResource($kategori))->response()->setStatusCode(201);
    }

    public function update(Request $request, KategoriKas $kategoriKas): KategoriKasResource
    {
        abort_if($kategoriKas->isSistem(), 422, 'Kategori sistem tidak dapat diubah.');

        $kategoriKas->update($request->validate([
            'nama' => ['required', 'string', 'max:255'],
        ]));

        return new KategoriKasResource($kategoriKas);
    }

    public function destroy(KategoriKas $kategoriKas): JsonResponse
    {
        abort_if($kategoriKas->isSistem(), 422, 'Kategori sistem tidak dapat dihapus.');
        abort_if($kategoriKas->transaksi()->exists(), 422, 'Kategori sudah dipakai di transaksi kas.');

        $kategoriKas->delete();

        return response()->json(['message' => 'Kategori dihapus.']);
    }
}
