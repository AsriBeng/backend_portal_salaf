<?php

namespace App\Http\Controllers;

use App\Enums\Gender;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

abstract class Controller
{
    /**
     * Data milik jenis kelamin lain dianggap tidak ada (404),
     * supaya pengurus putra tidak bisa membuka data putri dan sebaliknya.
     */
    protected function ensureGenderAccess(User $user, Gender|string|null $gender): void
    {
        abort_unless($user->canAccessGender($gender), 404);
    }

    /**
     * Jenis kelamin untuk data baru: admin wajib memilih (field "gender"),
     * pengurus lain otomatis memakai jenis kelaminnya sendiri.
     */
    protected function resolveGender(Request $request, string $field = 'gender'): Gender
    {
        $user = $request->user();

        if ($user->genderScope() !== null) {
            return $user->genderScope();
        }

        $request->validate([$field => ['required', Rule::enum(Gender::class)]]);

        return Gender::from($request->input($field));
    }

    protected function perPage(Request $request): int
    {
        return min(max((int) $request->query('per_page', 20), 1), 100);
    }
}
