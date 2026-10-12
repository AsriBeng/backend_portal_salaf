<div align="center">

<img src="public/logo-pondok-192.png" alt="Logo Pondok Pesantren As-Salafiyyah Asy-Syafi'iyyah" width="140">

# Portal Salaf API

**Backend REST API Aplikasi Portal Santri**<br>
Pondok Pesantren As-Salafiyyah Asy-Syafi'iyyah · Tambakberas Barat, Jombang

<p>
  <img src="https://img.shields.io/badge/Laravel-13-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel 13">
  <img src="https://img.shields.io/badge/PHP-8.3+-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.3+">
  <img src="https://img.shields.io/badge/MySQL-8-4479A1?style=for-the-badge&logo=mysql&logoColor=white" alt="MySQL 8">
  <img src="https://img.shields.io/badge/Auth-Sanctum-0b6b3a?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel Sanctum">
  <img src="https://img.shields.io/badge/Push-Firebase-FFCA28?style=for-the-badge&logo=firebase&logoColor=black" alt="Firebase">
</p>

<p>
  <a href="https://as-salafiyah.id"><img src="https://img.shields.io/badge/Website%20Pondok-as--salafiyah.id-0b6b3a?style=flat-square&logo=googlechrome&logoColor=white" alt="Website pondok"></a>
  <img src="https://img.shields.io/badge/tests-24%20passed-brightgreen?style=flat-square" alt="24 test lulus">
  <img src="https://img.shields.io/badge/client-Flutter%20(Android%20%26%20iOS)-02569B?style=flat-square&logo=flutter&logoColor=white" alt="Flutter">
</p>

<a href="#-fitur">Fitur</a> ·
<a href="#-peran-pengguna">Peran</a> ·
<a href="#-instalasi-lokal">Instalasi</a> ·
<a href="#-upload-ke-hosting">Hosting</a> ·
<a href="#-dokumentasi-api">API</a> ·
<a href="#-pembuat">Pembuat</a>

</div>

---

## ✨ Fitur

<table>
  <tr>
    <td width="50%" valign="top">

**🎓 Data Santri**
Data lengkap santri, orang tua, dan wali, plus dokumen KK, akta, dan KTP yang disimpan privat.

**🏠 Komplek & Kamar**
Komplek putra/putri dengan kapasitas per kamar. Santri otomatis ditolak jika kamar penuh.

**🪪 Kartu Santri Digital**
Kartu dengan QR code yang mengarah ke halaman verifikasi keaslian di web pondok.

**📢 Pengumuman**
Untuk semua santri, putra saja, atau putri saja.

  </td>
    <td width="50%" valign="top">

**🧾 Tagihan**
SPP dibuat otomatis setiap tanggal 1, iuran dibuat sesuai kebutuhan. Boleh dicicil.

**💳 Pembayaran**
Santri upload bukti, lalu bendahara menyetujui. Bendahara juga bisa input pembayaran tunai.

**📒 Kas Putra & Putri**
Kas terpisah, bertambah otomatis dari pembayaran. Pengeluaran tidak bisa melebihi saldo.

**🔔 Notifikasi**
Pengumuman baru, tagihan baru, dan status pembayaran dikirim ke HP lewat Firebase.

  </td>
  </tr>
</table>

## 👥 Peran Pengguna

| Peran | Cakupan | Login | Tugas utama |
|---|---|---|---|
| 🛡️ **Admin / Pengasuh** | Putra & putri | Username | Kendali penuh dan kas gabungan |
| 👔 **Ketua** | Sesuai gender | Username | Memantau data dan laporan, membuat pengumuman |
| 📝 **Sekretaris** | Sesuai gender | Username | Komplek, kamar, data dan akun santri |
| 💰 **Bendahara** | Sesuai gender | Username | Tagihan, verifikasi pembayaran, kas |
| 🎓 **Santri / Wali** | Data sendiri | NIS | Kartu, tagihan, upload bukti bayar |

> [!NOTE]
> Pengurus putra hanya melihat data santri putra, dan pengurus putri hanya data santri putri. Filter ini berjalan otomatis di semua endpoint. Data dari gender lain dibalas `404`.

## 🚀 Instalasi Lokal

**Kebutuhan:** PHP 8.3+, Composer, dan MySQL 8. Laragon sudah cukup.

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Buka `http://127.0.0.1:8000` untuk halaman verifikasi kartu. Jalankan test dengan perintah ini (memakai database `portal_salaf_test`):

```bash
php artisan test
```

> [!IMPORTANT]
> Seeder membuat akun admin awal. Kredensialnya ada di `database/seeders/DatabaseSeeder.php`, atau atur sendiri lewat `ADMIN_USERNAME` dan `ADMIN_PASSWORD` di `.env` sebelum menjalankan seeding. **Segera ganti password setelah login pertama.**

## 🌐 Upload ke Hosting

<details>
<summary><b>Langkah lengkap untuk shared hosting / cPanel</b> (klik untuk membuka)</summary>

<br>

**1. Upload proyek** ke folder di luar `public_html`, misalnya `/home/user/portal_salaf`.

**2. Arahkan document root** domain/subdomain ke `/home/user/portal_salaf/public`.
Jika document root tidak bisa diubah, salin isi folder `public` ke `public_html`, lalu ubah dua path di `public_html/index.php` menjadi `../portal_salaf/vendor/autoload.php` dan `../portal_salaf/bootstrap/app.php`.

**3. Buat database MySQL** di cPanel, lalu isi `.env`:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.domainanda.com

DB_DATABASE=nama_database
DB_USERNAME=user_database
DB_PASSWORD=password_database

QUEUE_CONNECTION=database
```

**4. Jalankan perintah ini** lewat Terminal cPanel atau SSH:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --seed --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

**5. Pasang cron job** yang berjalan setiap menit:

```
* * * * * cd /home/user/portal_salaf && php artisan schedule:run >> /dev/null 2>&1
```

**6. Beri izin tulis** pada folder `storage` dan `bootstrap/cache` (`chmod 775`).

</details>

> [!WARNING]
> - **`APP_URL` wajib benar**, karena dipakai untuk link QR di kartu santri.
> - **Cron job wajib dipasang.** Tanpa cron, SPP otomatis dan pengiriman notifikasi tidak berjalan.

### 🔔 Push Notification (Firebase)

1. Buka Firebase Console, lalu **Project settings → Service accounts → Generate new private key**.
2. Simpan file JSON-nya, misalnya di `storage/app/private/firebase.json`.
3. Isi di `.env`:
   ```env
   FCM_CREDENTIALS=storage/app/private/firebase.json
   ```

Tanpa file ini, notifikasi tetap tersimpan di aplikasi. Hanya push ke HP yang dilewati.

## 🧠 Aturan Penting

| Topik | Aturan |
|---|---|
| 🔑 Password awal santri | Tanggal lahir dengan format `ddmmyyyy` jika tidak diisi |
| 🧾 SPP | Dibuat otomatis tanggal 1 untuk santri aktif dan tidak bisa dobel. Jika nominalnya 0, SPP tidak dibuat |
| ✋ SPP manual | `php artisan spp:generate 2026-10` |
| 💳 Cicilan | Nominal bayar ≤ sisa tagihan dikurangi pembayaran yang masih menunggu |
| 📒 Kas | Saldo = masuk − keluar per gender. Pengeluaran ditolak jika melebihi saldo |
| 🔒 File | Foto, KK, akta, KTP, dan bukti bayar disimpan privat di `storage/app/private` dan hanya bisa diunduh lewat API sesuai hak akses |
| 🪪 Kartu hilang | `POST /santri/{id}/kartu/regenerate`. QR lama otomatis tidak berlaku |

## 📖 Dokumentasi API

**Base URL:** `{APP_URL}/api`

```http
Accept: application/json
Authorization: Bearer {token}
```

- **Error validasi** dibalas `422` dengan pesan berbahasa Indonesia: `{ "message": "...", "errors": { "field": ["..."] } }`
- **Daftar data** berpaginasi (`?page=`, `?per_page=` maks. 100) dengan isi `data`, `links`, dan `meta`.
- **Upload file** memakai `multipart/form-data`. Karena itu ubah data santri, pengumuman, dan pengaturan memakai method `POST`.

<details>
<summary><b>🔐 Autentikasi & Akun</b></summary>

| Method | Endpoint | Akses | Keterangan |
|---|---|---|---|
| `POST` | `/login` | publik | `username`, `password`, `device_name?` → `token`, `user` |
| `GET` | `/pondok` | publik | Nama, alamat, website, dan logo pondok |
| `GET` | `/me` | semua | Profil (santri: termasuk data santri) |
| `POST` | `/logout` | semua | `device_token?` untuk melepas token FCM |
| `PUT` | `/me/password` | semua | `password_lama`, `password`, `password_confirmation` |
| `POST` | `/device-tokens` | semua | `token`, `platform` (`android`/`ios`) |
| `GET` | `/dashboard` | semua | Ringkasan beranda sesuai peran |

</details>

<details>
<summary><b>🔔 Notifikasi & Pengumuman</b></summary>

| Method | Endpoint | Akses | Keterangan |
|---|---|---|---|
| `GET` | `/notifikasi` | semua | `?belum_dibaca=1` |
| `POST` | `/notifikasi/{id}/baca` | semua | Tandai satu notifikasi dibaca |
| `POST` | `/notifikasi/baca-semua` | semua | Tandai semua dibaca |
| `GET` | `/pengumuman` · `/pengumuman/{id}` | semua | Sesuai target |
| `GET` | `/pengumuman/{id}/lampiran` | semua | Unduh lampiran |
| `POST` | `/pengumuman` | admin, ketua, sekretaris | `judul`, `isi`, `target`, `lampiran?` |
| `POST` | `/pengumuman/{id}` | pembuat / admin | Ubah (`hapus_lampiran?`) |
| `DELETE` | `/pengumuman/{id}` | pembuat / admin | Hapus |

</details>

<details>
<summary><b>🎓 Santri, Kartu & Dokumen</b></summary>

| Method | Endpoint | Akses | Keterangan |
|---|---|---|---|
| `GET` | `/santri` | pengurus | `?search= ?status= ?komplek_id= ?kamar_id=` |
| `GET` | `/santri/{id}` | pengurus | NIK, alamat, dan dokumen hanya untuk admin & sekretaris |
| `POST` | `/santri` | admin, sekretaris | Tambah santri + akun (boleh sekalian upload dokumen) |
| `POST` | `/santri/{id}` | admin, sekretaris | Ubah data, `status`, `kamar_id` |
| `DELETE` | `/santri/{id}` | admin, sekretaris | Soft delete + nonaktifkan akun |
| `POST` | `/santri/{id}/dokumen` | admin, sekretaris | `foto_profil`, `file_kk`, `file_akta`, `file_ktp` |
| `GET` | `/santri/{id}/dokumen/{jenis}` | sesuai hak | Unduh dokumen |
| `POST` | `/santri/{id}/reset-password` | admin, sekretaris | `password?` (kosong = tanggal lahir) |
| `POST` | `/santri/{id}/aktif` | admin, sekretaris | `is_active` |
| `GET` | `/kartu-saya` | santri | Data kartu + `qr_url` |
| `GET` | `/santri/{id}/kartu` | admin, ketua, sekretaris | Data kartu santri |
| `POST` | `/santri/{id}/kartu/regenerate` | admin, sekretaris | Buat QR baru |

</details>

<details>
<summary><b>🏠 Komplek & Kamar</b></summary>

| Method | Endpoint | Akses | Keterangan |
|---|---|---|---|
| `GET` | `/komplek` · `/kamar` | pengurus | `/kamar?komplek_id=&tersedia=1` |
| `GET` | `/komplek/{id}` · `/kamar/{id}` | admin, ketua, sekretaris | Detail, daftar kamar / penghuni |
| `POST` `PUT` `DELETE` | `/komplek` · `/kamar` | admin, sekretaris | Kapasitas tidak bisa lebih kecil dari jumlah penghuni |

</details>

<details>
<summary><b>🧾 Tagihan & Pembayaran</b></summary>

| Method | Endpoint | Akses | Keterangan |
|---|---|---|---|
| `GET` | `/tagihan` | admin, ketua, bendahara, santri | `?status= ?belum_lunas=1 ?periode=YYYY-MM`, ada `ringkasan` |
| `GET` | `/tagihan/{id}` | sda | Detail + riwayat pembayaran |
| `POST` | `/tagihan` | admin, bendahara | Iuran: `jenis_tagihan_id`, `judul?`, `nominal?`, `gender?`, `santri_ids?` |
| `POST` | `/tagihan/generate-spp` | admin, bendahara | `periode?`, aman diulang |
| `PUT` `DELETE` | `/tagihan/{id}` | admin, bendahara | Hapus hanya jika belum ada pembayaran |
| `GET` | `/pembayaran` · `/pembayaran/{id}` | admin, ketua, bendahara, santri | `?status=menunggu` |
| `GET` | `/pembayaran/{id}/bukti` | sda | Unduh bukti |
| `POST` | `/pembayaran` | santri, admin, bendahara | Santri: `tagihan_id`, `nominal`, `bukti`. Bendahara: + `metode` |
| `POST` | `/pembayaran/{id}/setujui` | admin, bendahara | |
| `POST` | `/pembayaran/{id}/tolak` | admin, bendahara | `alasan` |
| `GET` `POST` `PUT` `DELETE` | `/jenis-tagihan` | lihat: + ketua | `nama`, `tipe` (`spp`/`iuran`), `nominal` |

</details>

<details>
<summary><b>📒 Kas & Laporan</b></summary>

| Method | Endpoint | Akses | Keterangan |
|---|---|---|---|
| `GET` | `/kas/saldo` | admin, ketua, bendahara | `putra`, `putri`, `total` |
| `GET` | `/kas` · `/kas/{id}` · `/kas/{id}/bukti` | sda | `?tipe= ?kategori_kas_id= ?dari= ?sampai=` |
| `POST` | `/kas` | admin, bendahara | `tipe`, `kategori_kas_id`, `nominal`, `keterangan`, `bukti?`, `gender` (admin) |
| `DELETE` | `/kas/{id}` | admin, bendahara | Hanya transaksi manual |
| `GET` `POST` `PUT` `DELETE` | `/kategori-kas` | lihat: + ketua | Kategori sistem terkunci |
| `GET` | `/laporan/keuangan` | admin, ketua, bendahara | `?dari= ?sampai= ?gender=` |
| `GET` | `/laporan/tunggakan` | admin, ketua, bendahara | Santri dengan tagihan belum lunas |
| `GET` | `/laporan/santri` | admin, ketua, sekretaris | Rekap per status & komplek |

</details>

<details>
<summary><b>🛡️ Admin</b></summary>

| Method | Endpoint | Keterangan |
|---|---|---|
| `GET` `POST` `PUT` `DELETE` | `/users` | Akun pengurus (`role`, `gender`). DELETE = nonaktifkan |
| `POST` | `/users/{id}/reset-password` | `password` |
| `GET` · `POST` | `/settings` | `nama_pondok`, `alamat_pondok`, `telepon_pondok`, `website_pondok`, `spp_jatuh_tempo_hari`, `logo` |

</details>

### 🌍 Halaman Web Publik

| Halaman | Keterangan |
|---|---|
| `/` | Informasi sistem verifikasi kartu santri + tautan ke website pondok |
| `/kartu/{kode}` | Hasil verifikasi kartu (tujuan QR code) |
| `/logo` | Logo pondok |

## 🗂️ Struktur Singkat

```
app/
├── Enums/                 Role, Gender, status tagihan & pembayaran
├── Http/Controllers/Api/  Endpoint per modul
├── Http/Resources/        Format respons JSON
├── Models/                Eloquent + filter gender (Concerns/ScopedByGender)
├── Notifications/         Pengumuman, tagihan, pembayaran (+ kanal FCM)
└── Services/              Logika tagihan, pembayaran, dan Firebase
resources/views/           Halaman awal & verifikasi kartu
routes/api.php             Semua endpoint API
routes/console.php         Jadwal SPP otomatis & antrean notifikasi
```

---

## 👨‍💻 Pembuat

<div align="center">

<a href="https://github.com/AsriBeng">
  <img src="https://github.com/AsriBeng.png" width="96" alt="AsriBeng" style="border-radius:50%">
</a>

### [@AsriBeng](https://github.com/AsriBeng)

Dibuat dengan 💚 untuk **Pondok Pesantren As-Salafiyyah Asy-Syafi'iyyah**

<a href="https://github.com/AsriBeng"><img src="https://img.shields.io/badge/GitHub-AsriBeng-181717?style=for-the-badge&logo=github" alt="GitHub AsriBeng"></a>
<a href="https://as-salafiyah.id"><img src="https://img.shields.io/badge/Pondok-as--salafiyah.id-0b6b3a?style=for-the-badge" alt="as-salafiyah.id"></a>

<sub>© 2026 Portal Salaf · Pondok Pesantren As-Salafiyyah Asy-Syafi'iyyah, Tambakberas Barat, Jombang</sub>

</div>
