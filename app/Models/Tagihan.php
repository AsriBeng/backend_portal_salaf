<?php

namespace App\Models;

use App\Enums\PembayaranStatus;
use App\Enums\TagihanStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('tagihan')]
#[Fillable(['santri_id', 'jenis_tagihan_id', 'judul', 'periode', 'nominal', 'terbayar', 'status', 'jatuh_tempo', 'created_by'])]
class Tagihan extends Model
{
    protected $attributes = [
        'terbayar' => 0,
        'status' => 'belum_bayar',
    ];

    protected function casts(): array
    {
        return [
            'periode' => 'date',
            'jatuh_tempo' => 'date',
            'nominal' => 'integer',
            'terbayar' => 'integer',
            'status' => TagihanStatus::class,
        ];
    }

    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class)->withTrashed();
    }

    public function jenisTagihan(): BelongsTo
    {
        return $this->belongsTo(JenisTagihan::class);
    }

    public function pembayaran(): HasMany
    {
        return $this->hasMany(Pembayaran::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereHas('santri', fn (Builder $q) => $q->visibleTo($user));
    }

    public function scopeBelumLunas(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('status'), '!=', TagihanStatus::Lunas);
    }

    public function sisa(): int
    {
        return max(0, $this->nominal - $this->terbayar);
    }

    /** Sisa tagihan dikurangi pembayaran yang masih menunggu verifikasi. */
    public function sisaBisaDibayar(): int
    {
        $menunggu = (int) $this->pembayaran()->where('status', PembayaranStatus::Menunggu)->sum('nominal');

        return max(0, $this->sisa() - $menunggu);
    }

    /** Hitung ulang status dari nilai terbayar. */
    public function refreshStatus(): void
    {
        $this->status = match (true) {
            $this->terbayar >= $this->nominal => TagihanStatus::Lunas,
            $this->terbayar > 0 => TagihanStatus::Dicicil,
            default => TagihanStatus::BelumBayar,
        };
    }
}
