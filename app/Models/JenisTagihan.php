<?php

namespace App\Models;

use App\Enums\JenisTagihanTipe;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('jenis_tagihan')]
#[Fillable(['nama', 'tipe', 'nominal', 'is_active'])]
class JenisTagihan extends Model
{
    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'tipe' => JenisTagihanTipe::class,
            'nominal' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function tagihan(): HasMany
    {
        return $this->hasMany(Tagihan::class);
    }
}
