<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

#[Table('settings', key: 'key', keyType: 'string', incrementing: false)]
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    /** Nilai bawaan jika belum diatur. */
    public const DEFAULTS = [
        'nama_pondok' => "Pondok Pesantren As-Salafiyyah Asy-Syafi'iyyah",
        'alamat_pondok' => 'Tambakberas Barat, Jombang',
        'telepon_pondok' => '',
        'website_pondok' => 'https://as-salafiyah.id',
        'logo' => null,
        'spp_jatuh_tempo_hari' => '10',
    ];

    public static function value(string $key): mixed
    {
        return static::values()[$key] ?? null;
    }

    /** @return array<string, mixed> */
    public static function values(): array
    {
        return Cache::rememberForever('settings', fn () => array_merge(
            self::DEFAULTS,
            static::query()->pluck('value', 'key')->all(),
        ));
    }

    public static function put(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('settings');
    }
}
