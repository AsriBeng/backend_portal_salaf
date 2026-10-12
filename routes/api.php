<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\JenisTagihanController;
use App\Http\Controllers\Api\KamarController;
use App\Http\Controllers\Api\KartuController;
use App\Http\Controllers\Api\KasController;
use App\Http\Controllers\Api\KategoriKasController;
use App\Http\Controllers\Api\KomplekController;
use App\Http\Controllers\Api\LaporanController;
use App\Http\Controllers\Api\NotifikasiController;
use App\Http\Controllers\Api\PembayaranController;
use App\Http\Controllers\Api\PengumumanController;
use App\Http\Controllers\Api\SantriController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\TagihanController;
use App\Http\Controllers\Api\UserController;
use App\Models\KasTransaksi;
use Illuminate\Support\Facades\Route;

Route::model('kas', KasTransaksi::class);

/*
| Publik
*/
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::get('/pondok', [SettingController::class, 'pondok']);

/*
| Semua pengguna yang login
*/
Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/me/password', [AuthController::class, 'changePassword']);
    Route::post('/device-tokens', [AuthController::class, 'registerDevice']);

    Route::get('/dashboard', DashboardController::class);

    Route::get('/notifikasi', [NotifikasiController::class, 'index']);
    Route::post('/notifikasi/baca-semua', [NotifikasiController::class, 'bacaSemua']);
    Route::post('/notifikasi/{id}/baca', [NotifikasiController::class, 'baca']);

    // Pengumuman: semua bisa melihat; admin, ketua, sekretaris bisa membuat
    Route::get('/pengumuman', [PengumumanController::class, 'index']);
    Route::get('/pengumuman/{pengumuman}', [PengumumanController::class, 'show']);
    Route::get('/pengumuman/{pengumuman}/lampiran', [PengumumanController::class, 'lampiran'])->name('pengumuman.lampiran');
    Route::middleware('role:admin,ketua,sekretaris')->group(function () {
        Route::post('/pengumuman', [PengumumanController::class, 'store']);
        Route::post('/pengumuman/{pengumuman}', [PengumumanController::class, 'update']); // POST agar bisa multipart
        Route::delete('/pengumuman/{pengumuman}', [PengumumanController::class, 'destroy']);
    });

    // Dokumen & foto santri (hak akses dicek di controller)
    Route::get('/santri/{santri}/dokumen/{jenis}', [SantriController::class, 'dokumen'])->name('santri.dokumen');

    // Tagihan & pembayaran: santri melihat miliknya, pengurus sesuai gender
    Route::middleware('role:admin,ketua,bendahara,santri')->group(function () {
        Route::get('/tagihan', [TagihanController::class, 'index']);
        Route::get('/tagihan/{tagihan}', [TagihanController::class, 'show']);
        Route::get('/pembayaran', [PembayaranController::class, 'index']);
        Route::get('/pembayaran/{pembayaran}', [PembayaranController::class, 'show']);
        Route::get('/pembayaran/{pembayaran}/bukti', [PembayaranController::class, 'bukti'])->name('pembayaran.bukti');
    });

    /*
    | Santri
    */
    Route::middleware('role:santri')->group(function () {
        Route::get('/kartu-saya', [KartuController::class, 'saya']);
    });

    Route::middleware('role:santri,admin,bendahara')->group(function () {
        // santri: upload bukti; admin/bendahara: input pembayaran langsung
        Route::post('/pembayaran', [PembayaranController::class, 'store']);
    });

    /*
    | Pengurus
    */
    Route::middleware('role:admin,ketua,sekretaris,bendahara')->group(function () {
        Route::get('/santri', [SantriController::class, 'index']);
        Route::get('/santri/{santri}', [SantriController::class, 'show']);
        Route::get('/komplek', [KomplekController::class, 'index']);
        Route::get('/kamar', [KamarController::class, 'index']);
    });

    Route::middleware('role:admin,ketua,sekretaris')->group(function () {
        Route::get('/komplek/{komplek}', [KomplekController::class, 'show']);
        Route::get('/kamar/{kamar}', [KamarController::class, 'show']);
        Route::get('/santri/{santri}/kartu', [KartuController::class, 'show']);
        Route::get('/laporan/santri', [LaporanController::class, 'santri']);
    });

    // Admin & sekretaris: komplek, kamar, data dan akun santri
    Route::middleware('role:admin,sekretaris')->group(function () {
        Route::post('/komplek', [KomplekController::class, 'store']);
        Route::put('/komplek/{komplek}', [KomplekController::class, 'update']);
        Route::delete('/komplek/{komplek}', [KomplekController::class, 'destroy']);

        Route::post('/kamar', [KamarController::class, 'store']);
        Route::put('/kamar/{kamar}', [KamarController::class, 'update']);
        Route::delete('/kamar/{kamar}', [KamarController::class, 'destroy']);

        Route::post('/santri', [SantriController::class, 'store']);
        Route::post('/santri/{santri}', [SantriController::class, 'update']); // POST agar bisa multipart
        Route::delete('/santri/{santri}', [SantriController::class, 'destroy']);
        Route::post('/santri/{santri}/dokumen', [SantriController::class, 'uploadDokumen']);
        Route::post('/santri/{santri}/reset-password', [SantriController::class, 'resetPassword']);
        Route::post('/santri/{santri}/aktif', [SantriController::class, 'setAktif']);
        Route::post('/santri/{santri}/kartu/regenerate', [KartuController::class, 'regenerate']);
    });

    // Keuangan: admin, ketua (lihat), bendahara
    Route::middleware('role:admin,ketua,bendahara')->group(function () {
        Route::get('/jenis-tagihan', [JenisTagihanController::class, 'index']);
        Route::get('/kategori-kas', [KategoriKasController::class, 'index']);
        Route::get('/kas/saldo', [KasController::class, 'saldo']);
        Route::get('/kas', [KasController::class, 'index']);
        Route::get('/kas/{kas}', [KasController::class, 'show']);
        Route::get('/kas/{kas}/bukti', [KasController::class, 'bukti'])->name('kas.bukti');
        Route::get('/laporan/keuangan', [LaporanController::class, 'keuangan']);
        Route::get('/laporan/tunggakan', [LaporanController::class, 'tunggakan']);
    });

    Route::middleware('role:admin,bendahara')->group(function () {
        Route::post('/jenis-tagihan', [JenisTagihanController::class, 'store']);
        Route::put('/jenis-tagihan/{jenisTagihan}', [JenisTagihanController::class, 'update']);
        Route::delete('/jenis-tagihan/{jenisTagihan}', [JenisTagihanController::class, 'destroy']);

        Route::post('/tagihan', [TagihanController::class, 'store']);
        Route::post('/tagihan/generate-spp', [TagihanController::class, 'generateSpp']);
        Route::put('/tagihan/{tagihan}', [TagihanController::class, 'update']);
        Route::delete('/tagihan/{tagihan}', [TagihanController::class, 'destroy']);

        Route::post('/pembayaran/{pembayaran}/setujui', [PembayaranController::class, 'setujui']);
        Route::post('/pembayaran/{pembayaran}/tolak', [PembayaranController::class, 'tolak']);

        Route::post('/kas', [KasController::class, 'store']);
        Route::delete('/kas/{kas}', [KasController::class, 'destroy']);

        Route::post('/kategori-kas', [KategoriKasController::class, 'store']);
        Route::put('/kategori-kas/{kategoriKas}', [KategoriKasController::class, 'update']);
        Route::delete('/kategori-kas/{kategoriKas}', [KategoriKasController::class, 'destroy']);
    });

    /*
    | Admin / pengasuh
    */
    Route::middleware('role:admin')->group(function () {
        Route::apiResource('users', UserController::class);
        Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword']);

        Route::get('/settings', [SettingController::class, 'index']);
        Route::post('/settings', [SettingController::class, 'update']); // POST agar bisa multipart (logo)
    });
});
