# Portal Salaf: API

Backend REST API (Laravel 13 + Sanctum) untuk aplikasi mobile Portal Santri (Flutter).
Fitur: akun pengurus dan santri, komplek dan kamar, data santri dan dokumen, kartu santri dengan verifikasi QR, tagihan SPP/iuran, pembayaran dengan upload bukti, kas putra/putri, pengumuman, dan push notification.

## Menjalankan di lokal (Laragon)

```bash
composer install
cp .env.example .env        # lalu sesuaikan DB_*
php artisan key:generate
php artisan migrate --seed
php artisan test
```

Seeder membuat akun admin awal (lihat `database/seeders/DatabaseSeeder.php`, atau atur lewat `ADMIN_USERNAME` / `ADMIN_PASSWORD` di `.env` sebelum seeding). **Segera ganti passwordnya setelah login pertama.**

## Upload ke hosting (shared hosting / cPanel)

1. **Upload proyek** ke folder di luar `public_html`, misalnya `/home/user/portal_salaf`.
2. **Document root** domain/subdomain diarahkan ke `/home/user/portal_salaf/public`.
   Jika document root tidak bisa diubah, salin isi folder `public` ke `public_html`, lalu ubah dua path di `public_html/index.php` agar mengarah ke `../portal_salaf/vendor/autoload.php` dan `../portal_salaf/bootstrap/app.php`.
3. **Buat database MySQL** di cPanel, lalu isi `.env`:
   ```
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://api.domainanda.com
   DB_DATABASE=...
   DB_USERNAME=...
   DB_PASSWORD=...
   QUEUE_CONNECTION=database
   ```
   `APP_URL` wajib benar karena dipakai untuk link QR kartu santri.
4. Jalankan lewat Terminal cPanel / SSH:
   ```bash
   composer install --no-dev --optimize-autoloader
   php artisan key:generate      # hanya jika APP_KEY masih kosong
   php artisan migrate --seed --force
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   ```
5. **Cron job** (wajib, untuk SPP otomatis tiap tanggal 1 dan pengiriman notifikasi), setiap menit:
   ```
   * * * * * cd /home/user/portal_salaf && php artisan schedule:run >> /dev/null 2>&1
   ```
6. **Push notification**: unduh *service account JSON* dari Firebase Console (Project settings → Service accounts), simpan misalnya di `storage/app/private/firebase.json`, lalu isi `FCM_CREDENTIALS=storage/app/private/firebase.json`. Tanpa file ini, notifikasi tetap tersimpan di aplikasi, hanya push ke HP yang dilewati.
7. Pastikan folder `storage` dan `bootstrap/cache` bisa ditulis (chmod 775).

Semua file (foto, KK, akta, KTP, bukti bayar) disimpan **privat** di `storage/app/private` dan hanya bisa diunduh lewat API sesuai hak akses. Folder ini tidak perlu `storage:link`.

## Konsep penting

- **Peran**: `admin` (pengasuh, melihat putra + putri), `ketua`, `sekretaris`, `bendahara` (masing-masing putra atau putri), `santri` (juga dipakai wali).
- **Filter gender otomatis**: pengurus hanya melihat data sesuai jenis kelaminnya. Data gender lain dibalas `404`. Admin boleh memakai `?gender=L|P`.
- **Login**: `username` berisi NIS (santri) atau username (pengurus). Password awal santri = tanggal lahir `ddmmyyyy` jika tidak diisi.
- **SPP** dibuat otomatis tanggal 1 untuk santri aktif (`php artisan spp:generate [YYYY-MM]` untuk manual). Nominal diatur di Jenis Tagihan (tipe `spp`); jika 0, SPP tidak dibuat.
- **Pembayaran**: boleh dicicil. Santri upload bukti → `menunggu` → bendahara `setujui`/`tolak`. Input langsung oleh bendahara/admin langsung `disetujui`. Pembayaran yang disetujui otomatis masuk kas sesuai gender santri.
- **Kas**: saldo = masuk − keluar per gender. Pengeluaran ditolak jika melebihi saldo.

## Format API

- Base URL: `{APP_URL}/api`. Header: `Accept: application/json`, `Authorization: Bearer {token}`.
- Error validasi: `422` dengan `{ message, errors: { field: [pesan] } }` (Bahasa Indonesia).
- Daftar: berpaginasi (`?page=`, `?per_page=` maks 100) dengan `data`, `links`, `meta`.
- Upload file: `multipart/form-data`. Karena itu ubah data santri, pengumuman, dan pengaturan memakai `POST`.

## Daftar endpoint

| Method | Endpoint | Akses | Keterangan |
|---|---|---|---|
| POST | `/login` | publik | `username`, `password`, `device_name?` → `token`, `user` |
| GET | `/pondok` | publik | Nama, alamat, logo pondok (layar login) |
| GET | `/me` | semua | Profil (santri: termasuk data santri) |
| POST | `/logout` | semua | `device_token?` untuk melepas token FCM |
| PUT | `/me/password` | semua | `password_lama`, `password`, `password_confirmation` |
| POST | `/device-tokens` | semua | `token`, `platform` (android/ios) |
| GET | `/dashboard` | semua | Ringkasan sesuai peran |
| GET | `/notifikasi` | semua | `?belum_dibaca=1`; ada `belum_dibaca` (jumlah) |
| POST | `/notifikasi/{id}/baca`, `/notifikasi/baca-semua` | semua | Tandai dibaca |
| GET | `/pengumuman`, `/pengumuman/{id}` | semua | Sesuai target (semua/putra/putri) |
| GET | `/pengumuman/{id}/lampiran` | semua | Unduh lampiran |
| POST | `/pengumuman` | admin, ketua, sekretaris | `judul`, `isi`, `target`, `lampiran?` |
| POST / DELETE | `/pengumuman/{id}` | pembuat / admin | Ubah (`hapus_lampiran?`) / hapus |
| GET | `/kartu-saya` | santri | Data kartu + `qr_url` |
| GET | `/santri/{id}/kartu` | admin, ketua, sekretaris | Data kartu santri |
| POST | `/santri/{id}/kartu/regenerate` | admin, sekretaris | QR baru (kartu lama tidak berlaku) |
| GET | `/santri` | pengurus | `?search= ?status=aktif\|lulus\|keluar\|semua ?komplek_id= ?kamar_id=` |
| GET | `/santri/{id}` | pengurus | NIK/alamat/dokumen hanya untuk admin & sekretaris |
| POST | `/santri` | admin, sekretaris | Tambah santri + akun (boleh sekalian upload dokumen) |
| POST | `/santri/{id}` | admin, sekretaris | Ubah data (termasuk `status`, `kamar_id`) |
| DELETE | `/santri/{id}` | admin, sekretaris | Hapus (soft delete) + nonaktifkan akun |
| POST | `/santri/{id}/dokumen` | admin, sekretaris | `foto_profil`, `file_kk`, `file_akta`, `file_ktp` |
| GET | `/santri/{id}/dokumen/{jenis}` | sesuai hak | Unduh dokumen |
| POST | `/santri/{id}/reset-password` | admin, sekretaris | `password?` (kosong = tanggal lahir) |
| POST | `/santri/{id}/aktif` | admin, sekretaris | `is_active` akun login |
| GET | `/komplek`, `/kamar` | pengurus | `/kamar?komplek_id=&tersedia=1` |
| GET | `/komplek/{id}`, `/kamar/{id}` | admin, ketua, sekretaris | Detail + daftar kamar / penghuni |
| POST/PUT/DELETE | `/komplek`, `/kamar` | admin, sekretaris | Kelola; kapasitas tidak bisa < penghuni |
| GET | `/tagihan` | admin, ketua, bendahara, santri | `?status= ?belum_lunas=1 ?periode=YYYY-MM ?santri_id= ?search=`; ada `ringkasan` |
| GET | `/tagihan/{id}` | sda | Detail + riwayat pembayaran |
| POST | `/tagihan` | admin, bendahara | Iuran massal: `jenis_tagihan_id`, `judul?`, `nominal?`, `jatuh_tempo?`, `gender?`, `santri_ids?` |
| POST | `/tagihan/generate-spp` | admin, bendahara | `periode?` (YYYY-MM), aman diulang |
| PUT / DELETE | `/tagihan/{id}` | admin, bendahara | Ubah / hapus (jika belum ada pembayaran) |
| GET | `/pembayaran`, `/pembayaran/{id}` | admin, ketua, bendahara, santri | `?status=menunggu` dst. |
| GET | `/pembayaran/{id}/bukti` | sda | Unduh bukti |
| POST | `/pembayaran` | santri, admin, bendahara | Santri: `tagihan_id`, `nominal`, `bukti`. Bendahara: + `metode` (tunai/transfer) |
| POST | `/pembayaran/{id}/setujui` | admin, bendahara | |
| POST | `/pembayaran/{id}/tolak` | admin, bendahara | `alasan` |
| GET/POST/PUT/DELETE | `/jenis-tagihan` | lihat: admin, ketua, bendahara; kelola: admin, bendahara | `nama`, `tipe` (spp/iuran), `nominal` |
| GET | `/kas/saldo` | admin, ketua, bendahara | `putra`, `putri`, `total` |
| GET | `/kas`, `/kas/{id}`, `/kas/{id}/bukti` | sda | `?tipe= ?kategori_kas_id= ?dari= ?sampai=` |
| POST | `/kas` | admin, bendahara | `tipe`, `kategori_kas_id`, `nominal`, `keterangan`, `tanggal?`, `bukti?`, `gender` (admin) |
| DELETE | `/kas/{id}` | admin, bendahara | Hanya transaksi manual |
| GET/POST/PUT/DELETE | `/kategori-kas` | sda | Kategori sistem tidak bisa diubah |
| GET | `/laporan/keuangan` | admin, ketua, bendahara | `?dari= ?sampai= ?gender=` |
| GET | `/laporan/tunggakan` | admin, ketua, bendahara | Santri dengan tagihan belum lunas |
| GET | `/laporan/santri` | admin, ketua, sekretaris | Rekap per status & komplek |
| GET/POST/PUT/DELETE | `/users` | admin | Akun pengurus (`role`, `gender`); DELETE = nonaktifkan |
| POST | `/users/{id}/reset-password` | admin | `password` |
| GET / POST | `/settings` | admin | `nama_pondok`, `alamat_pondok`, `telepon_pondok`, `spp_jatuh_tempo_hari`, `logo` |

Halaman web (tanpa login):

- `GET /kartu/{kode}`: halaman verifikasi keaslian kartu (tujuan QR).
- `GET /logo`: logo pondok.
