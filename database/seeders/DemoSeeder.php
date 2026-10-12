<?php

namespace Database\Seeders;

use App\Enums\Gender;
use App\Enums\Role;
use App\Models\Kamar;
use App\Models\Komplek;
use App\Models\Santri;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Data contoh untuk mencoba aplikasi: akun pengurus, komplek, kamar, dan santri.
 *
 *   php artisan db:seed --class=DemoSeeder
 *
 * Aman dijalankan berulang (data yang sudah ada tidak dibuat dobel).
 * JANGAN dijalankan di server produksi yang berisi data asli.
 *
 * Akun yang dibuat (password semua akun pengurus: DEMO_PASSWORD):
 *   sekretaris_putra, sekretaris_putri, bendahara_putra, bendahara_putri
 * Akun santri contoh: NIS 2025001 dengan password DEMO_PASSWORD.
 * Santri lain: password = tanggal lahir (ddmmyyyy).
 */
class DemoSeeder extends Seeder
{
    public const DEMO_PASSWORD = 'demo12345';

    public function run(): void
    {
        $this->call(DatabaseSeeder::class);

        DB::transaction(function () {
            $this->pengurus();
            $kamar = $this->komplekDanKamar();
            $this->santri($kamar);
        });

        $this->command?->info('Data demo siap. Lihat komentar di DemoSeeder untuk daftar akun.');
    }

    private function pengurus(): void
    {
        $akun = [
            ['sekretaris_putra', 'Ust. Ahmad Syaifuddin', Role::Sekretaris, Gender::Putra],
            ['sekretaris_putri', 'Ustdz. Siti Maryam', Role::Sekretaris, Gender::Putri],
            ['bendahara_putra', 'Ust. Muhammad Ridwan', Role::Bendahara, Gender::Putra],
            ['bendahara_putri', 'Ustdz. Nur Hidayah', Role::Bendahara, Gender::Putri],
        ];

        foreach ($akun as [$username, $nama, $role, $gender]) {
            User::firstOrCreate(['username' => $username], [
                'name' => $nama,
                'password' => self::DEMO_PASSWORD,
                'role' => $role,
                'gender' => $gender,
                'phone' => '0812'.random_int(10000000, 99999999),
            ]);
        }
    }

    /**
     * @return array<string, Kamar> kunci "Gender:Nama Kamar"
     */
    private function komplekDanKamar(): array
    {
        $data = [
            [Gender::Putra, 'Komplek Al-Ikhlas', 'Asrama putra, gedung utara', ['Kamar 1' => 6, 'Kamar 2' => 6, 'Kamar 3' => 4]],
            [Gender::Putra, 'Komplek An-Nur', 'Asrama putra, gedung selatan', ['Kamar 1' => 8, 'Kamar 2' => 8]],
            [Gender::Putri, 'Komplek Fatimah Az-Zahra', 'Asrama putri', ['Kamar 1' => 6, 'Kamar 2' => 6, 'Kamar 3' => 3]],
        ];

        $kamar = [];
        foreach ($data as [$gender, $nama, $keterangan, $daftarKamar]) {
            $komplek = Komplek::firstOrCreate(
                ['nama' => $nama, 'gender' => $gender],
                ['keterangan' => $keterangan],
            );

            foreach ($daftarKamar as $namaKamar => $kapasitas) {
                $kamar["{$gender->value}:{$nama}:{$namaKamar}"] = Kamar::firstOrCreate(
                    ['komplek_id' => $komplek->id, 'nama' => $namaKamar],
                    ['kapasitas' => $kapasitas],
                );
            }
        }

        return $kamar;
    }

    /** @param  array<string, Kamar>  $kamar */
    private function santri(array $kamar): void
    {
        // [NIS, nama, L/P, tempat lahir, tanggal lahir, kamar, ayah, ibu, kecamatan, kabupaten, provinsi, status]
        $daftar = [
            ['2025001', 'Ahmad Fauzi Rahman', 'L', 'Jombang', '2011-03-15', 'Komplek Al-Ikhlas:Kamar 1', 'Abdullah Hasyim', 'Aminah', 'Diwek', 'Jombang', 'Jawa Timur', 'aktif'],
            ['2025002', 'Muhammad Hasan Basri', 'L', 'Kediri', '2010-08-20', 'Komplek Al-Ikhlas:Kamar 1', 'Basri Hamid', 'Khotijah', 'Pare', 'Kediri', 'Jawa Timur', 'aktif'],
            ['2025003', 'Muhammad Iqbal Maulana', 'L', 'Mojokerto', '2011-01-09', 'Komplek Al-Ikhlas:Kamar 1', 'Sholeh', 'Rofiah', 'Mojosari', 'Mojokerto', 'Jawa Timur', 'aktif'],
            ['2025004', 'Ahmad Zainul Arifin', 'L', 'Lamongan', '2009-11-30', 'Komplek Al-Ikhlas:Kamar 2', 'Zainuddin', 'Mutmainnah', 'Paciran', 'Lamongan', 'Jawa Timur', 'aktif'],
            ['2025005', 'Rizky Maulana Yusuf', 'L', 'Nganjuk', '2012-05-04', 'Komplek Al-Ikhlas:Kamar 2', 'Yusuf Efendi', 'Halimah', 'Kertosono', 'Nganjuk', 'Jawa Timur', 'aktif'],
            ['2025006', 'Ali Ridho Mustofa', 'L', 'Tuban', '2010-02-17', 'Komplek Al-Ikhlas:Kamar 3', 'Mustofa Kamal', 'Sumiati', 'Jenu', 'Tuban', 'Jawa Timur', 'aktif'],
            ['2025007', 'Fathul Huda', 'L', 'Blitar', '2011-07-22', 'Komplek An-Nur:Kamar 1', 'Huda Anwar', 'Lailatul Badriyah', 'Srengat', 'Blitar', 'Jawa Timur', 'aktif'],
            ['2025008', 'Ilham Nur Hidayat', 'L', 'Jombang', '2012-09-01', null, 'Hidayat', 'Nur Aini', 'Peterongan', 'Jombang', 'Jawa Timur', 'aktif'],
            ['2025009', 'Syamsul Ma\'arif', 'L', 'Gresik', '2006-04-12', null, 'Ma\'arif', 'Zulaikha', 'Manyar', 'Gresik', 'Jawa Timur', 'lulus'],
            ['2025101', 'Siti Aisyah Putri', 'P', 'Jombang', '2011-06-10', 'Komplek Fatimah Az-Zahra:Kamar 1', 'Mahmud', 'Fatimatuz Zahro', 'Tembelang', 'Jombang', 'Jawa Timur', 'aktif'],
            ['2025102', 'Nur Laila Rahmawati', 'P', 'Kediri', '2010-12-25', 'Komplek Fatimah Az-Zahra:Kamar 1', 'Rahmat', 'Laili', 'Gurah', 'Kediri', 'Jawa Timur', 'aktif'],
            ['2025103', 'Zahrotul Mufidah', 'P', 'Sidoarjo', '2011-10-03', 'Komplek Fatimah Az-Zahra:Kamar 2', 'Mufid', 'Nafisah', 'Krian', 'Sidoarjo', 'Jawa Timur', 'aktif'],
            ['2025104', 'Ummu Kultsum', 'P', 'Pasuruan', '2012-02-14', 'Komplek Fatimah Az-Zahra:Kamar 3', 'Sulaiman', 'Hafsah', 'Bangil', 'Pasuruan', 'Jawa Timur', 'aktif'],
            ['2025105', 'Alfiyah Nur Azizah', 'P', 'Surabaya', '2010-08-08', 'Komplek Fatimah Az-Zahra:Kamar 3', 'Aziz', 'Rohmah', 'Wonokromo', 'Surabaya', 'Jawa Timur', 'aktif'],
        ];

        $pekerjaan = ['Petani', 'Wiraswasta', 'Pedagang', 'Guru', 'Karyawan swasta', 'Nelayan'];

        foreach ($daftar as $i => [$nis, $nama, $jk, $tempat, $lahir, $kamarKey, $ayah, $ibu, $kec, $kab, $prov, $status]) {
            if (Santri::withTrashed()->where('nis', $nis)->exists()) {
                continue;
            }

            $gender = Gender::from($jk);
            $user = User::firstOrCreate(['username' => $nis], [
                'name' => $nama,
                // Santri pertama memakai password demo, sisanya tanggal lahir (ddmmyyyy)
                'password' => $nis === '2025001' ? self::DEMO_PASSWORD : date('dmY', strtotime($lahir)),
                'role' => Role::Santri,
                'gender' => $gender,
            ]);

            $santri = new Santri([
                'nis' => $nis,
                'nama_lengkap' => $nama,
                'nik' => '3517'.str_pad((string) ($i + 1), 12, '0', STR_PAD_LEFT),
                'no_kk' => '3517'.str_pad((string) (900 + $i), 12, '0', STR_PAD_LEFT),
                'tempat_lahir' => $tempat,
                'tanggal_lahir' => $lahir,
                'jenis_kelamin' => $gender,
                'anak_ke' => ($i % 3) + 1,
                'jumlah_saudara' => ($i % 4) + 1,
                'alamat' => 'Dusun '.['Krajan', 'Sumberejo', 'Kauman', 'Ngemplak', 'Karangrejo'][$i % 5],
                'rt' => (string) (($i % 5) + 1),
                'rw' => (string) (($i % 3) + 1),
                'kelurahan' => ['Tambakrejo', 'Sumbermulyo', 'Kepuhrejo', 'Mojongapit', 'Sukodadi'][$i % 5],
                'kecamatan' => $kec,
                'kabupaten' => $kab,
                'provinsi' => $prov,
                'kode_pos' => (string) (61400 + $i),
                'nama_ayah' => $ayah,
                'pekerjaan_ayah' => $pekerjaan[$i % count($pekerjaan)],
                'no_telepon_ayah' => '0813'.str_pad((string) (10000000 + $i * 7919), 8, '0', STR_PAD_LEFT),
                'nama_ibu' => $ibu,
                'pekerjaan_ibu' => 'Ibu rumah tangga',
                'no_telepon_ibu' => '0857'.str_pad((string) (20000000 + $i * 6007), 8, '0', STR_PAD_LEFT),
                'kamar_id' => $kamarKey ? $kamar["{$jk}:{$kamarKey}"]->id : null,
                'status' => $status,
                'tanggal_masuk' => $status === 'lulus' ? '2019-07-15' : '2025-07-14',
                'tanggal_keluar' => $status === 'lulus' ? '2025-06-20' : null,
            ]);
            $santri->user()->associate($user);
            $santri->save();
        }
    }
}
