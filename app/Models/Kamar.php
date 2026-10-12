<?php

namespace App\Models;

use App\Enums\SantriStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('kamar')]
#[Fillable(['komplek_id', 'nama', 'kapasitas'])]
class Kamar extends Model
{
    protected function casts(): array
    {
        return [
            'kapasitas' => 'integer',
        ];
    }

    public function komplek(): BelongsTo
    {
        return $this->belongsTo(Komplek::class);
    }

    public function santri(): HasMany
    {
        return $this->hasMany(Santri::class);
    }

    public function santriAktif(): HasMany
    {
        return $this->santri()->where('status', SantriStatus::Aktif);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereHas('komplek', fn (Builder $q) => $q->visibleTo($user));
    }

    /** Sisa tempat; santri yang sedang dipindah tidak ikut dihitung. */
    public function sisaTempat(?int $kecualiSantriId = null): int
    {
        $terisi = $this->santriAktif()
            ->when($kecualiSantriId, fn (Builder $q) => $q->whereKeyNot($kecualiSantriId))
            ->count();

        return $this->kapasitas - $terisi;
    }
}
