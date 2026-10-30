<?php

namespace App\Enums;

enum PengumumanTarget: string
{
    case Semua = 'semua';
    case Putra = 'putra';
    case Putri = 'putri';
    // santri yang sudah lulus atau keluar
    case Alumni = 'alumni';

    public function gender(): ?Gender
    {
        return match ($this) {
            self::Semua, self::Alumni => null,
            self::Putra => Gender::Putra,
            self::Putri => Gender::Putri,
        };
    }
}
