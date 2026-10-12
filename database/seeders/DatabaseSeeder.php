<?php

namespace Database\Seeders;

use App\Enums\JenisTagihanTipe;
use App\Enums\KasTipe;
use App\Enums\Role;
use App\Models\JenisTagihan;
use App\Models\KategoriKas;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Data awal: akun admin, kategori kas, dan jenis tagihan SPP.
     * Aman dijalankan ulang.
     */
    public function run(): void
    {
        // Ganti password admin segera setelah login pertama (menu Profil > Ubah Password)
        User::firstOrCreate(
            ['username' => env('ADMIN_USERNAME', 'admin')],
            [
                'name' => 'Admin / Pengasuh',
                'password' => env('ADMIN_PASSWORD', 'admin12345'),
                'role' => Role::Admin,
                'gender' => null,
            ],
        );

        KategoriKas::pembayaranSantri();

        foreach (['Iuran / Donasi', 'Lain-lain'] as $nama) {
            KategoriKas::firstOrCreate(['nama' => $nama, 'tipe' => KasTipe::Masuk]);
        }

        foreach (['Konsumsi', 'Listrik & Air', 'Perawatan Gedung', 'Kegiatan Santri', 'Ziarah', 'Lain-lain'] as $nama) {
            KategoriKas::firstOrCreate(['nama' => $nama, 'tipe' => KasTipe::Keluar]);
        }

        // Nominal SPP diatur admin/bendahara lewat menu Jenis Tagihan
        JenisTagihan::firstOrCreate(
            ['tipe' => JenisTagihanTipe::Spp],
            ['nama' => 'SPP', 'nominal' => 0, 'is_active' => true],
        );
    }
}
