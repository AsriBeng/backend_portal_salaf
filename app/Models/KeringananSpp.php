<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Nominal SPP khusus untuk satu santri selama rentang bulan tertentu. */
#[Table('keringanan_spp')]
#[Fillable(['santri_id', 'nominal', 'mulai', 'sampai', 'keterangan', 'created_by'])]
class KeringananSpp extends Model
{
    protected function casts(): array
    {
        return [
            'nominal' => 'integer',
            'mulai' => 'date',
            'sampai' => 'date',
        ];
    }

    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class)->withTrashed();
    }

    public function scopeBerlakuPada(Builder $query, CarbonInterface $periode): Builder
    {
        return $query->whereDate('mulai', '<=', $periode)
            ->where(fn (Builder $q) => $q->whereNull('sampai')->orWhereDate('sampai', '>=', $periode));
    }

    /** Keringanan lain milik santri yang sama yang rentangnya bertumpuk. */
    public function scopeBertumpuk(Builder $query, int $santriId, CarbonInterface $mulai, ?CarbonInterface $sampai): Builder
    {
        return $query->where('santri_id', $santriId)
            ->where(fn (Builder $q) => $q->whereNull('sampai')->orWhereDate('sampai', '>=', $mulai))
            ->when($sampai, fn (Builder $q) => $q->whereDate('mulai', '<=', $sampai));
    }
}
