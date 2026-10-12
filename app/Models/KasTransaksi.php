<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\KasTipe;
use App\Models\Concerns\ScopedByGender;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('kas_transaksi')]
#[Fillable(['gender', 'tipe', 'kategori_kas_id', 'nominal', 'tanggal', 'keterangan', 'pembayaran_id', 'bukti', 'created_by'])]
class KasTransaksi extends Model
{
    use ScopedByGender;

    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'tipe' => KasTipe::class,
            'nominal' => 'integer',
            'tanggal' => 'date',
        ];
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(KategoriKas::class, 'kategori_kas_id');
    }

    public function pembayaran(): BelongsTo
    {
        return $this->belongsTo(Pembayaran::class);
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Saldo kas satu jenis kelamin: total masuk dikurangi total keluar. */
    public static function saldo(Gender $gender): int
    {
        $masuk = (int) static::where('gender', $gender->value)->where('tipe', KasTipe::Masuk)->sum('nominal');
        $keluar = (int) static::where('gender', $gender->value)->where('tipe', KasTipe::Keluar)->sum('nominal');

        return $masuk - $keluar;
    }
}
