<?php

namespace App\Support;

class Rupiah
{
    public static function format(int|float $nominal): string
    {
        return 'Rp '.number_format($nominal, 0, ',', '.');
    }
}
