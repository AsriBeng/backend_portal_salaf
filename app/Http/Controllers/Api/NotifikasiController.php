<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotifikasiController extends Controller
{
    /** ?belum_dibaca=1 */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $notifikasi = $user->notifications()
            ->when($request->boolean('belum_dibaca'), fn ($q) => $q->whereNull('read_at'))
            ->paginate($this->perPage($request));

        $notifikasi->getCollection()->transform(fn (DatabaseNotification $n) => [
            'id' => $n->id,
            ...$n->data,
            'dibaca' => $n->read_at !== null,
            'created_at' => $n->created_at,
        ]);

        return response()->json([
            ...$notifikasi->toArray(),
            'belum_dibaca' => $user->unreadNotifications()->count(),
        ]);
    }

    public function baca(Request $request, string $id): JsonResponse
    {
        $request->user()->notifications()->findOrFail($id)->markAsRead();

        return response()->json(['message' => 'Notifikasi ditandai sudah dibaca.']);
    }

    public function bacaSemua(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['message' => 'Semua notifikasi ditandai sudah dibaca.']);
    }
}
