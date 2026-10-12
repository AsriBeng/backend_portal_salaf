<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /** Login santri memakai NIS, pengurus memakai username. */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::where('username', $data['username'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'username' => 'NIS/username atau password salah.',
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'username' => 'Akun Anda dinonaktifkan. Hubungi admin atau sekretaris.',
            ]);
        }

        $token = $user->createToken($data['device_name'] ?? 'mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user->load('santri.kamar.komplek')),
        ]);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load('santri.kamar.komplek'));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->validate(['device_token' => ['nullable', 'string']]);

        if ($request->filled('device_token')) {
            $request->user()->deviceTokens()->where('token', $request->input('device_token'))->delete();
        }

        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Berhasil keluar.']);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'password_lama' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(6)],
        ]);

        $user = $request->user();
        $user->update(['password' => $data['password']]);

        // Keluarkan perangkat lain, tetap login di perangkat ini
        $user->tokens()->whereKeyNot($user->currentAccessToken()->id)->delete();

        return response()->json(['message' => 'Password berhasil diubah.']);
    }

    /** Daftarkan token FCM perangkat untuk push notification. */
    public function registerDevice(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'platform' => ['required', 'in:android,ios'],
        ]);

        DeviceToken::updateOrCreate(
            ['token' => $data['token']],
            ['user_id' => $request->user()->id, 'platform' => $data['platform']],
        );

        return response()->json(['message' => 'Perangkat terdaftar.']);
    }
}
