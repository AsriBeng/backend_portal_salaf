<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Ketua = 'ketua';
    case Sekretaris = 'sekretaris';
    case Bendahara = 'bendahara';
    case Santri = 'santri';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin / Pengasuh',
            self::Ketua => 'Ketua',
            self::Sekretaris => 'Sekretaris',
            self::Bendahara => 'Bendahara',
            self::Santri => 'Santri',
        };
    }

    /** Peran pengurus yang dibatasi per jenis kelamin. */
    public static function pengurus(): array
    {
        return [self::Ketua, self::Sekretaris, self::Bendahara];
    }
}
