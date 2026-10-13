<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Foto profil pengurus. Bisa diganti oleh pengurus sendiri atau oleh admin.
 * Foto santri tidak diatur di sini (memakai foto di data santri, dikelola admin/sekretaris).
 */
class FotoPenggunaController extends Controller
{
    /** Semua pengguna yang login boleh melihat foto pengurus (tampil di pengumuman, dsb.). */
    public function tampil(User $user): StreamedResponse
    {
        abort_unless($user->foto && Storage::exists($user->foto), 404);

        return Storage::response($user->foto, null, ['Cache-Control' => 'private, max-age=86400']);
    }

    public function simpanSaya(Request $request): UserResource
    {
        return $this->simpan($request, $request->user());
    }

    public function hapusSaya(Request $request): UserResource
    {
        return $this->hapus($request->user());
    }

    /** Admin mengganti foto pengurus lain. */
    public function simpanUntuk(Request $request, User $user): UserResource
    {
        abort_if($user->isSantri(), 404);

        return $this->simpan($request, $user);
    }

    public function hapusUntuk(User $user): UserResource
    {
        abort_if($user->isSantri(), 404);

        return $this->hapus($user);
    }

    private function simpan(Request $request, User $user): UserResource
    {
        $request->validate(['foto' => ['required', 'image', 'max:2048']]);

        $lama = $user->foto;
        $user->foto = $request->file('foto')->store("pengguna/{$user->id}");
        $user->save();

        if ($lama) {
            Storage::delete($lama);
        }

        return new UserResource($user->load('santri.kamar.komplek'));
    }

    private function hapus(User $user): UserResource
    {
        if ($user->foto) {
            Storage::delete($user->foto);
            $user->foto = null;
            $user->save();
        }

        return new UserResource($user->load('santri.kamar.komplek'));
    }
}
