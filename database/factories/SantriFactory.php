<?php

namespace Database\Factories;

use App\Enums\Gender;
use App\Enums\SantriStatus;
use App\Models\Santri;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Santri>
 */
class SantriFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nis = (string) fake()->unique()->numerify('2026####');

        return [
            'user_id' => User::factory()->santri()->state(['username' => $nis]),
            'nis' => $nis,
            'nama_lengkap' => fake()->name('male'),
            'tempat_lahir' => fake()->city(),
            'tanggal_lahir' => fake()->dateTimeBetween('-18 years', '-10 years')->format('Y-m-d'),
            'jenis_kelamin' => Gender::Putra,
            'alamat' => fake()->streetAddress(),
            'kelurahan' => fake()->citySuffix(),
            'kecamatan' => fake()->city(),
            'kabupaten' => fake()->city(),
            'provinsi' => fake()->state(),
            'nama_ayah' => fake()->name('male'),
            'nama_ibu' => fake()->name('female'),
            'status' => SantriStatus::Aktif,
            'tanggal_masuk' => now()->subYear()->toDateString(),
        ];
    }

    public function putri(): static
    {
        return $this->state(fn () => [
            'jenis_kelamin' => Gender::Putri,
            'nama_lengkap' => fake()->name('female'),
        ])->afterCreating(fn (Santri $santri) => $santri->user->update(['gender' => Gender::Putri]));
    }
}
