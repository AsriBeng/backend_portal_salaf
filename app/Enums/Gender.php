<?php

namespace App\Enums;

enum Gender: string
{
    case Putra = 'L';
    case Putri = 'P';

    public function label(): string
    {
        return $this === self::Putra ? 'Putra' : 'Putri';
    }

    /** Nilai target pengumuman ("putra" / "putri"). */
    public function target(): string
    {
        return $this === self::Putra ? 'putra' : 'putri';
    }
}
