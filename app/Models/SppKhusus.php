<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Nominal SPP khusus (potongan / bebas SPP) untuk sekelompok santri selama rentang bulan tertentu. */
#[Table('spp_khusus')]
#[Fillable(['nama', 'nominal', 'mulai', 'sampai', 'keterangan', 'created_by'])]
class SppKhusus extends Model
{
    protected function casts(): array
    {
        return [
            'nominal' => 'integer',
            'mulai' => 'date',
            'sampai' => 'date',
        ];
    }

    public function santri(): BelongsToMany
    {
        return $this->belongsToMany(Santri::class, 'spp_khusus_santri');
    }

    public function scopeBerlakuPada(Builder $query, CarbonInterface $periode): Builder
    {
        return $query->whereDate('spp_khusus.mulai', '<=', $periode)
            ->where(fn (Builder $q) => $q->whereNull('spp_khusus.sampai')->orWhereDate('spp_khusus.sampai', '>=', $periode));
    }

    /** SPP khusus yang rentangnya bertumpuk dengan rentang ini. */
    public function scopeBertumpuk(Builder $query, CarbonInterface $mulai, ?CarbonInterface $sampai): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('spp_khusus.sampai')->orWhereDate('spp_khusus.sampai', '>=', $mulai))
            ->when($sampai, fn (Builder $q) => $q->whereDate('spp_khusus.mulai', '<=', $sampai));
    }

    public function berlaku(): bool
    {
        return $this->mulai->lte(now()) && ($this->sampai === null || $this->sampai->gte(now()->startOfMonth()));
    }
}
