<?php

namespace Database\Seeders;

use App\Enums\Gender;
use App\Models\Kamar;
use App\Models\Komplek;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Komplek dan kamar sesuai "Rekap Komplek dan Kamar Santri.xlsx".
 *
 *   php artisan db:seed --class=KomplekKamarSeeder
 *
 * Aman dijalankan berulang (data yang sudah ada tidak dibuat dobel).
 */
class KomplekKamarSeeder extends Seeder
{
    public const KAPASITAS_KAMAR = 12;

    /** @var array<string, array{0: Gender, 1: array<int, string>}> komplek => [gender, daftar kamar] */
    public const KOMPLEK = [
        'Utara' => [Gender::Putra, ['Utara 1', 'Utara 2', 'Utara 3']],
        'Selatan' => [Gender::Putra, ['Selatan 1', 'Selatan 2', 'Selatan 3', 'Selatan 4']],
        'Selatan Atas' => [Gender::Putra, ['Atas 1', 'Atas 2']],
        'Blok A' => [Gender::Putri, ['Al-Fatimah', 'Al-Khadijah', 'Al-Masyitoh', 'Al-Mustofa', 'An-Nuriyah']],
        'Blok B' => [Gender::Putri, ['Al-Jadidah 1', 'Al-Jadidah 2', 'Al-Jadidah 3']],
    ];

    public function run(): void
    {
        DB::transaction(function () {
            foreach (self::KOMPLEK as $nama => [$gender, $daftarKamar]) {
                $komplek = Komplek::firstOrCreate(['nama' => $nama, 'gender' => $gender]);

                foreach ($daftarKamar as $namaKamar) {
                    Kamar::firstOrCreate(
                        ['komplek_id' => $komplek->id, 'nama' => $namaKamar],
                        ['kapasitas' => self::KAPASITAS_KAMAR],
                    );
                }
            }
        });
    }
}
