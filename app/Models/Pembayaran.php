<?php

namespace App\Models;

use App\Enums\PembayaranStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Table('pembayaran')]
#[Fillable(['tagihan_id', 'nominal', 'metode', 'sumber', 'bukti', 'status', 'alasan_tolak', 'tanggal_bayar', 'catatan', 'created_by', 'verified_by', 'verified_at'])]
class Pembayaran extends Model
{
    protected function casts(): array
    {
        return [
            'nominal' => 'integer',
            'status' => PembayaranStatus::class,
            'tanggal_bayar' => 'date',
            'verified_at' => 'datetime',
        ];
    }

    public function tagihan(): BelongsTo
    {
        return $this->belongsTo(Tagihan::class);
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function verifikator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function kasTransaksi(): HasOne
    {
        return $this->hasOne(KasTransaksi::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereHas('tagihan', fn (Builder $q) => $q->visibleTo($user));
    }
}
