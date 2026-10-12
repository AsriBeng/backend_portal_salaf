<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Membatasi query sesuai jenis kelamin pengurus yang login.
 * Admin melihat semua; pengurus putra hanya data putra, pengurus putri hanya data putri.
 */
trait ScopedByGender
{
    /** Nama kolom jenis kelamin di tabel model ini. */
    public function genderColumn(): string
    {
        return 'gender';
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $gender = $user->genderScope();

        return $gender === null
            ? $query
            : $query->where($this->qualifyColumn($this->genderColumn()), $gender->value);
    }

    /** Filter opsional ?gender=L|P untuk admin. */
    public function scopeGender(Builder $query, ?string $gender): Builder
    {
        return $gender ? $query->where($this->qualifyColumn($this->genderColumn()), $gender) : $query;
    }
}
