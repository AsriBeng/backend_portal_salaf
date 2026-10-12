<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'username', 'password', 'role', 'gender', 'phone', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => Role::class,
            'gender' => Gender::class,
            'is_active' => 'boolean',
        ];
    }

    public function santri(): HasOne
    {
        return $this->hasOne(Santri::class);
    }

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    public function hasRole(Role ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function isSantri(): bool
    {
        return $this->role === Role::Santri;
    }

    /**
     * Jenis kelamin yang membatasi data yang boleh dilihat pengguna ini.
     * Null berarti tidak dibatasi (admin / pengasuh).
     */
    public function genderScope(): ?Gender
    {
        return $this->isAdmin() ? null : $this->gender;
    }

    /** Apakah pengguna boleh mengakses data milik jenis kelamin tertentu. */
    public function canAccessGender(Gender|string|null $gender): bool
    {
        $scope = $this->genderScope();

        return $scope === null || $scope === ($gender instanceof Gender ? $gender : Gender::tryFrom((string) $gender));
    }

    /**
     * Token FCM tujuan push notification.
     *
     * @return array<int, string>
     */
    public function routeNotificationForFcm(): array
    {
        return $this->deviceTokens()->pluck('token')->all();
    }
}
