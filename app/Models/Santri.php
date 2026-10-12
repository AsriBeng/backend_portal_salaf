<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\SantriStatus;
use App\Models\Concerns\ScopedByGender;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Table('santri')]
#[Guarded(['id', 'user_id', 'card_token', 'foto_profil', 'file_kk', 'file_akta', 'file_ktp'])]
class Santri extends Model
{
    use HasFactory, ScopedByGender, SoftDeletes;

    /** Kolom dokumen yang bisa diunggah. */
    public const DOKUMEN = ['foto_profil', 'file_kk', 'file_akta', 'file_ktp'];

    /** Sama dengan default kolom di database. */
    protected $attributes = [
        'status' => 'aktif',
        'agama' => 'Islam',
        'kewarganegaraan' => 'WNI',
    ];

    protected static function booted(): void
    {
        static::creating(function (Santri $santri) {
            $santri->card_token ??= static::newCardToken();
        });
    }

    public static function newCardToken(): string
    {
        do {
            $token = Str::random(32);
        } while (static::withTrashed()->where('card_token', $token)->exists());

        return $token;
    }

    protected function casts(): array
    {
        return [
            'jenis_kelamin' => Gender::class,
            'status' => SantriStatus::class,
            'tanggal_lahir' => 'date',
            'tanggal_masuk' => 'date',
            'tanggal_keluar' => 'date',
            'anak_ke' => 'integer',
            'jumlah_saudara' => 'integer',
        ];
    }

    public function genderColumn(): string
    {
        return 'jenis_kelamin';
    }

    /** Santri hanya melihat dirinya sendiri, pengurus sesuai jenis kelaminnya. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isSantri()) {
            return $query->where($this->qualifyColumn('user_id'), $user->id);
        }

        $gender = $user->genderScope();

        return $gender === null ? $query : $query->where($this->qualifyColumn('jenis_kelamin'), $gender->value);
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('status'), SantriStatus::Aktif);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function kamar(): BelongsTo
    {
        return $this->belongsTo(Kamar::class);
    }

    public function tagihan(): HasMany
    {
        return $this->hasMany(Tagihan::class);
    }

    public function isAktif(): bool
    {
        return $this->status === SantriStatus::Aktif;
    }

    public function cardUrl(): string
    {
        return url('/kartu/'.$this->card_token);
    }
}
