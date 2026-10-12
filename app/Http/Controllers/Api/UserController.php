<?php

namespace App\Http\Controllers\Api;

use App\Enums\Gender;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Manajemen akun pengurus (admin, ketua, sekretaris, bendahara). Khusus admin.
 * Akun santri dikelola lewat SantriController.
 */
class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $users = User::where('role', '!=', Role::Santri)
            ->when($request->query('role'), fn ($q, $role) => $q->where('role', $role))
            ->when($request->query('gender'), fn ($q, $g) => $q->where('gender', $g))
            ->when($request->query('search'), fn ($q, $s) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$s}%")
                ->orWhere('username', 'like', "%{$s}%")))
            ->orderBy('role')
            ->orderBy('name')
            ->paginate($this->perPage($request));

        return UserResource::collection($users);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $user = User::create($data);

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function show(User $user): UserResource
    {
        $this->ensurePengurus($user);

        return new UserResource($user);
    }

    public function update(Request $request, User $user): UserResource
    {
        $this->ensurePengurus($user);

        $data = $this->validated($request, $user);

        abort_if(
            $user->is($request->user()) && (! ($data['is_active'] ?? true) || ($data['role'] ?? 'admin') !== 'admin'),
            422,
            'Anda tidak dapat menonaktifkan atau mengubah peran akun sendiri.',
        );

        $user->update($data);

        if (! $user->is_active) {
            $user->tokens()->delete();
        }

        return new UserResource($user);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->ensurePengurus($user);
        abort_if($user->is($request->user()), 422, 'Anda tidak dapat menghapus akun sendiri.');

        // Akun yang sudah punya riwayat transaksi cukup dinonaktifkan
        $user->tokens()->delete();
        $user->update(['is_active' => false]);

        return response()->json(['message' => 'Akun dinonaktifkan.']);
    }

    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $this->ensurePengurus($user);

        $data = $request->validate(['password' => ['required', Password::min(6)]]);

        $user->update(['password' => $data['password']]);
        $user->tokens()->delete();

        return response()->json(['message' => 'Password berhasil direset.']);
    }

    private function ensurePengurus(User $user): void
    {
        abort_if($user->isSantri(), 404);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?User $user = null): array
    {
        $pengurus = array_map(fn (Role $r) => $r->value, [Role::Admin, ...Role::pengurus()]);

        $data = $request->validate([
            'name' => [$user ? 'sometimes' : 'required', 'string', 'max:255'],
            'username' => [$user ? 'sometimes' : 'required', 'string', 'max:50', 'alpha_dash', Rule::unique('users')->ignore($user)],
            'password' => [$user ? 'prohibited' : 'required', Password::min(6)],
            'role' => [$user ? 'sometimes' : 'required', Rule::in($pengurus)],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'phone' => ['nullable', 'string', 'max:20'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $role = $data['role'] ?? $user?->role->value;

        if ($role === Role::Admin->value) {
            // Admin / pengasuh melihat putra dan putri
            $data['gender'] = null;
        } elseif (empty($data['gender'] ?? $user?->gender?->value)) {
            throw ValidationException::withMessages([
                'gender' => 'Jenis kelamin wajib diisi untuk ketua, sekretaris, dan bendahara.',
            ]);
        }

        return $data;
    }
}
