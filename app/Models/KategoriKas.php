<?php

namespace App\Models;

use App\Enums\KasTipe;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('kategori_kas')]
#[Fillable(['nama', 'tipe'])]
class KategoriKas extends Model
{
    /** Kategori sistem untuk pemasukan dari pembayaran santri. */
    public const PEMBAYARAN_SANTRI = 'pembayaran-santri';

    protected function casts(): array
    {
        return [
            'tipe' => KasTipe::class,
        ];
    }

    public function transaksi(): HasMany
    {
        return $this->hasMany(KasTransaksi::class);
    }

    public function isSistem(): bool
    {
        return $this->kode !== null;
    }

    public static function pembayaranSantri(): self
    {
        return static::firstOrCreate(
            ['kode' => self::PEMBAYARAN_SANTRI],
            ['nama' => 'Pembayaran Santri', 'tipe' => KasTipe::Masuk],
        );
    }
}
