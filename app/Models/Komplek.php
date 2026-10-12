<?php

namespace App\Models;

use App\Enums\Gender;
use App\Models\Concerns\ScopedByGender;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Table('komplek')]
#[Fillable(['nama', 'gender', 'keterangan'])]
class Komplek extends Model
{
    use ScopedByGender;

    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
        ];
    }

    public function kamar(): HasMany
    {
        return $this->hasMany(Kamar::class);
    }

    public function santri(): HasManyThrough
    {
        return $this->hasManyThrough(Santri::class, Kamar::class);
    }
}
