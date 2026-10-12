<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Enums\SantriStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\SantriRequest;
use App\Http\Resources\SantriResource;
use App\Models\Kamar;
use App\Models\Santri;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SantriController extends Controller
{
    /** Filter: ?search= ?status= ?komplek_id= ?kamar_id= ?gender= (admin) */
    public function index(Request $request): AnonymousResourceCollection
    {
        $santri = Santri::visibleTo($request->user())
            ->gender($request->query('gender'))
            ->when($request->query('status', 'aktif'), fn ($q, $s) => $s === 'semua' ? $q : $q->where('status', $s))
            ->when($request->query('kamar_id'), fn ($q, $id) => $q->where('kamar_id', $id))
            ->when($request->query('komplek_id'), fn ($q, $id) => $q->whereHas('kamar', fn ($q) => $q->where('komplek_id', $id)))
            ->when($request->query('search'), fn ($q, $s) => $q->where(fn ($q) => $q
                ->where('nama_lengkap', 'like', "%{$s}%")
                ->orWhere('nis', 'like', "%{$s}%")))
            ->with('kamar.komplek')
            ->orderBy('nama_lengkap')
            ->paginate($this->perPage($request));

        return SantriResource::collection($santri);
    }

    public function store(SantriRequest $request): JsonResponse
    {
        $data = $request->validated();
        $gender = $this->resolveGender($request, 'jenis_kelamin');
        $this->pastikanKamar($data['kamar_id'] ?? null, $gender->value);

        $santri = DB::transaction(function () use ($request, $data, $gender) {
            $user = User::create([
                'name' => $data['nama_lengkap'],
                'username' => $data['nis'],
                'password' => $data['password'] ?? date('dmY', strtotime($data['tanggal_lahir'])),
                'role' => Role::Santri,
                'gender' => $gender,
                'phone' => $data['no_telepon'] ?? null,
            ]);

            $santri = new Santri(Arr::except($data, ['password', ...Santri::DOKUMEN]));
            $santri->jenis_kelamin = $gender;
            $santri->user()->associate($user);
            $santri->save();

            $this->simpanDokumen($request, $santri);

            return $santri;
        });

        return (new SantriResource($santri->load('kamar.komplek', 'user')))->response()->setStatusCode(201);
    }

    public function show(Request $request, Santri $santri): SantriResource
    {
        $this->pastikanAkses($request, $santri);

        return new SantriResource($santri->load('kamar.komplek', 'user'));
    }

    public function update(SantriRequest $request, Santri $santri): SantriResource
    {
        $this->pastikanAkses($request, $santri);

        $data = $request->validated();

        $diaktifkanLagi = ($data['status'] ?? null) === SantriStatus::Aktif->value && ! $santri->isAktif();

        if (array_key_exists('kamar_id', $data) || $diaktifkanLagi) {
            $this->pastikanKamar($data['kamar_id'] ?? $santri->kamar_id, $santri->jenis_kelamin->value, $santri->id);
        }

        if (isset($data['status']) && $data['status'] !== SantriStatus::Aktif->value) {
            $data['tanggal_keluar'] ??= $santri->tanggal_keluar ?? now()->toDateString();
        } elseif (($data['status'] ?? null) === SantriStatus::Aktif->value) {
            $data['tanggal_keluar'] = null;
        }

        DB::transaction(function () use ($request, $santri, $data) {
            $santri->update(Arr::except($data, Santri::DOKUMEN));

            $santri->user->update(array_filter([
                'name' => $data['nama_lengkap'] ?? null,
                'username' => $data['nis'] ?? null,
            ]));

            $this->simpanDokumen($request, $santri);
        });

        return new SantriResource($santri->refresh()->load('kamar.komplek', 'user'));
    }

    /** Hapus (soft delete) santri dan nonaktifkan akunnya. */
    public function destroy(Request $request, Santri $santri): JsonResponse
    {
        $this->pastikanAkses($request, $santri);

        DB::transaction(function () use ($santri) {
            $santri->user->tokens()->delete();
            $santri->user->update(['is_active' => false]);
            $santri->delete();
        });

        return response()->json(['message' => 'Data santri dihapus.']);
    }

    public function resetPassword(Request $request, Santri $santri): JsonResponse
    {
        $this->pastikanAkses($request, $santri);

        $data = $request->validate(['password' => ['nullable', Password::min(6)]]);
        $password = $data['password'] ?? $santri->tanggal_lahir->format('dmY');

        $santri->user->update(['password' => $password, 'is_active' => true]);
        $santri->user->tokens()->delete();

        return response()->json([
            'message' => isset($data['password'])
                ? 'Password santri berhasil direset.'
                : 'Password santri direset ke tanggal lahir (format ddmmyyyy).',
        ]);
    }

    /** Aktifkan / nonaktifkan akun login santri. */
    public function setAktif(Request $request, Santri $santri): JsonResponse
    {
        $this->pastikanAkses($request, $santri);

        $aktif = $request->validate(['is_active' => ['required', 'boolean']])['is_active'];
        $santri->user->update(['is_active' => $aktif]);

        if (! $aktif) {
            $santri->user->tokens()->delete();
        }

        return response()->json(['message' => $aktif ? 'Akun santri diaktifkan.' : 'Akun santri dinonaktifkan.']);
    }

    /** Unggah satu atau beberapa dokumen (foto_profil, file_kk, file_akta, file_ktp). */
    public function uploadDokumen(Request $request, Santri $santri): SantriResource
    {
        $this->pastikanAkses($request, $santri);

        $request->validate([
            'foto_profil' => ['nullable', 'image', 'max:2048'],
            'file_kk' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
            'file_akta' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
            'file_ktp' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
        ]);

        if (! $request->hasAny(Santri::DOKUMEN)) {
            throw ValidationException::withMessages(['dokumen' => 'Pilih minimal satu dokumen untuk diunggah.']);
        }

        $this->simpanDokumen($request, $santri);

        return new SantriResource($santri->refresh()->load('kamar.komplek'));
    }

    /**
     * Unduh dokumen dari storage privat.
     * Foto profil: semua pengurus sesuai gender + santri sendiri.
     * KK, akta, KTP: hanya admin, sekretaris, dan santri sendiri.
     */
    public function dokumen(Request $request, Santri $santri, string $jenis): StreamedResponse
    {
        abort_unless(in_array($jenis, Santri::DOKUMEN, true), 404);

        $user = $request->user();
        $milikSendiri = $user->id === $santri->user_id;

        if (! $milikSendiri) {
            abort_if($user->isSantri(), 404);
            $this->ensureGenderAccess($user, $santri->jenis_kelamin);
            abort_if($jenis !== 'foto_profil' && ! $user->hasRole(Role::Admin, Role::Sekretaris), 403);
        }

        $path = $santri->{$jenis};
        abort_unless($path && Storage::exists($path), 404, 'Dokumen belum diunggah.');

        return Storage::response($path);
    }

    private function pastikanAkses(Request $request, Santri $santri): void
    {
        $this->ensureGenderAccess($request->user(), $santri->jenis_kelamin);
    }

    /** Kamar harus sesuai jenis kelamin santri dan masih ada tempat. */
    private function pastikanKamar(?int $kamarId, string $gender, ?int $santriId = null): void
    {
        if (! $kamarId) {
            return;
        }

        $kamar = Kamar::with('komplek')->findOrFail($kamarId);

        if ($kamar->komplek->gender->value !== $gender) {
            throw ValidationException::withMessages(['kamar_id' => 'Kamar tidak sesuai dengan jenis kelamin santri.']);
        }

        if ($kamar->sisaTempat($santriId) <= 0) {
            throw ValidationException::withMessages(['kamar_id' => "Kamar {$kamar->nama} sudah penuh (kapasitas {$kamar->kapasitas})."]);
        }
    }

    private function simpanDokumen(Request $request, Santri $santri): void
    {
        foreach (Santri::DOKUMEN as $jenis) {
            if (! $request->hasFile($jenis)) {
                continue;
            }

            $lama = $santri->{$jenis};
            $santri->{$jenis} = $request->file($jenis)->store("santri/{$santri->id}");

            if ($lama) {
                Storage::delete($lama);
            }
        }

        $santri->save();
    }
}
