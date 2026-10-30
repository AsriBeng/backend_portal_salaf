<?php

namespace App\Models;

use App\Enums\PengumumanTarget;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('pengumuman')]
#[Fillable(['judul', 'isi', 'target', 'lampiran', 'created_by', 'published_at'])]
class Pengumuman extends Model
{
    protected function casts(): array
    {
        return [
            'target' => PengumumanTarget::class,
            'published_at' => 'datetime',
        ];
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Admin melihat semuanya. Santri aktif: "semua" + jenis kelaminnya. Alumni (santri lulus/keluar):
     * "semua" + "alumni". Pengurus: "semua" + jenis kelaminnya + "alumni".
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $gender = $user->genderScope();

        if ($gender === null) {
            return $query;
        }

        $target = match (true) {
            ! $user->isSantri() => [$gender->target(), PengumumanTarget::Alumni->value],
            $user->santri?->isAktif() ?? false => [$gender->target()],
            default => [PengumumanTarget::Alumni->value],
        };

        return $query->whereIn('target', [PengumumanTarget::Semua->value, ...$target]);
    }
}
