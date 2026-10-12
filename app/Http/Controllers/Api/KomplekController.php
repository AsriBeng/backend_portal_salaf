<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\KomplekResource;
use App\Models\Komplek;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class KomplekController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $komplek = Komplek::visibleTo($request->user())
            ->gender($request->query('gender'))
            ->withCount('kamar')
            ->withSum('kamar', 'kapasitas')
            ->orderBy('gender')
            ->orderBy('nama')
            ->get();

        return KomplekResource::collection($komplek);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'keterangan' => ['nullable', 'string'],
        ]);

        $komplek = Komplek::create([...$data, 'gender' => $this->resolveGender($request)]);

        return (new KomplekResource($komplek))->response()->setStatusCode(201);
    }

    public function show(Request $request, Komplek $komplek): KomplekResource
    {
        $this->ensureGenderAccess($request->user(), $komplek->gender);

        $komplek->load(['kamar' => fn ($q) => $q->withCount('santriAktif')->orderBy('nama')]);

        return new KomplekResource($komplek);
    }

    public function update(Request $request, Komplek $komplek): KomplekResource
    {
        $this->ensureGenderAccess($request->user(), $komplek->gender);

        $komplek->update($request->validate([
            'nama' => ['sometimes', 'string', 'max:255'],
            'keterangan' => ['nullable', 'string'],
        ]));

        return new KomplekResource($komplek);
    }

    public function destroy(Request $request, Komplek $komplek): JsonResponse
    {
        $this->ensureGenderAccess($request->user(), $komplek->gender);
        abort_if($komplek->kamar()->exists(), 422, 'Komplek masih memiliki kamar. Hapus kamarnya terlebih dahulu.');

        $komplek->delete();

        return response()->json(['message' => 'Komplek dihapus.']);
    }
}
