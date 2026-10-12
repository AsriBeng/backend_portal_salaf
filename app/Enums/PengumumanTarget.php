<?php

namespace App\Enums;

enum PengumumanTarget: string
{
    case Semua = 'semua';
    case Putra = 'putra';
    case Putri = 'putri';

    public function gender(): ?Gender
    {
        return match ($this) {
            self::Semua => null,
            self::Putra => Gender::Putra,
            self::Putri => Gender::Putri,
        };
    }
}
