<?php

namespace App\Http\Controllers\Api;

use App\Enums\PengumumanTarget;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Resources\PengumumanResource;
use App\Models\Pengumuman;
use App\Models\User;
use App\Notifications\PengumumanBaru;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PengumumanController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $pengumuman = Pengumuman::visibleTo($request->user())
            ->where('published_at', '<=', now())
            ->when($request->query('target'), fn ($q, $t) => $q->where('target', $t))
            ->when($request->query('search'), fn ($q, $s) => $q->where('judul', 'like', "%{$s}%"))
            ->with('pembuat')
            ->latest('published_at')
            ->paginate($this->perPage($request));

        return PengumumanResource::collection($pengumuman);
    }

    public function show(Request $request, Pengumuman $pengumuman): PengumumanResource
    {
        $this->pastikanBisaLihat($request->user(), $pengumuman);

        return new PengumumanResource($pengumuman->load('pembuat'));
    }

    /** Pengurus putra hanya bisa menargetkan "semua" atau "putra"; putri sebaliknya. Admin bebas. */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $user = $request->user();

        $pengumuman = Pengumuman::create([
            ...$data,
            'lampiran' => $request->file('lampiran')?->store('pengumuman'),
            'created_by' => $user->id,
            'published_at' => now(),
        ]);

        Notification::send($this->penerima($pengumuman, $user), new PengumumanBaru($pengumuman));

        return (new PengumumanResource($pengumuman->load('pembuat')))->response()->setStatusCode(201);
    }

    public function update(Request $request, Pengumuman $pengumuman): PengumumanResource
    {
        $this->pastikanBisaUbah($request->user(), $pengumuman);

        $data = $this->validated($request, $pengumuman);

        if ($request->hasFile('lampiran')) {
            if ($pengumuman->lampiran) {
                Storage::delete($pengumuman->lampiran);
            }
            $data['lampiran'] = $request->file('lampiran')->store('pengumuman');
        } elseif ($request->boolean('hapus_lampiran') && $pengumuman->lampiran) {
            Storage::delete($pengumuman->lampiran);
            $data['lampiran'] = null;
        }

        $pengumuman->update($data);

        return new PengumumanResource($pengumuman->load('pembuat'));
    }

    public function destroy(Request $request, Pengumuman $pengumuman): JsonResponse
    {
        $this->pastikanBisaUbah($request->user(), $pengumuman);

        if ($pengumuman->lampiran) {
            Storage::delete($pengumuman->lampiran);
        }

        $pengumuman->delete();

        return response()->json(['message' => 'Pengumuman dihapus.']);
    }

    public function lampiran(Request $request, Pengumuman $pengumuman): StreamedResponse
    {
        $this->pastikanBisaLihat($request->user(), $pengumuman);
        abort_unless($pengumuman->lampiran && Storage::exists($pengumuman->lampiran), 404);

        return Storage::response($pengumuman->lampiran);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Pengumuman $pengumuman = null): array
    {
        $req = $pengumuman ? 'sometimes' : 'required';

        $data = $request->validate([
            'judul' => [$req, 'string', 'max:255'],
            'isi' => [$req, 'string'],
            'target' => [$req, Rule::enum(PengumumanTarget::class)],
            'lampiran' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $gender = $request->user()->genderScope();
        $target = isset($data['target']) ? PengumumanTarget::from($data['target']) : null;

        if ($gender && $target && $target->gender() !== null && $target->gender() !== $gender) {
            throw ValidationException::withMessages([
                'target' => 'Anda hanya dapat membuat pengumuman untuk semua atau '.strtolower($gender->label()).'.',
            ]);
        }

        unset($data['lampiran']);

        return $data;
    }

    private function pastikanBisaLihat(User $user, Pengumuman $pengumuman): void
    {
        abort_unless(Pengumuman::whereKey($pengumuman->id)->visibleTo($user)->exists(), 404);
    }

    private function pastikanBisaUbah(User $user, Pengumuman $pengumuman): void
    {
        $this->pastikanBisaLihat($user, $pengumuman);
        abort_unless($user->isAdmin() || $pengumuman->created_by === $user->id, 403, 'Hanya pembuat atau admin yang dapat mengubah pengumuman ini.');
    }

    /** Semua pengguna aktif yang berhak melihat pengumuman, kecuali pembuatnya. */
    private function penerima(Pengumuman $pengumuman, User $pembuat)
    {
        $gender = $pengumuman->target->gender();

        return User::where('is_active', true)
            ->whereKeyNot($pembuat->id)
            ->when($gender, fn ($q) => $q->where(fn ($q) => $q
                ->where('role', Role::Admin)
                ->orWhere('gender', $gender->value)))
            ->get();
    }
}
