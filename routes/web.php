<?php

use App\Http\Controllers\VerifikasiKartuController;
use Illuminate\Support\Facades\Route;

// Halaman awal: selamat datang Portal Salaf + penjelasan verifikasi kartu santri
Route::get('/', [VerifikasiKartuController::class, 'beranda']);

// Halaman verifikasi keaslian kartu santri (tujuan QR code)
Route::get('/kartu/{token}', [VerifikasiKartuController::class, 'show'])->middleware('throttle:60,1');
Route::get('/kartu/{token}/foto', [VerifikasiKartuController::class, 'foto'])->middleware('throttle:60,1');
Route::get('/logo', [VerifikasiKartuController::class, 'logo']);

// Service worker push notification versi web. Firebase mencarinya di root situs,
// sedangkan filenya ikut hasil build Flutter di public/app.
Route::get('/firebase-messaging-sw.js', function () {
    $sw = public_path('app/firebase-messaging-sw.js');
    abort_unless(is_file($sw), 404);

    return response()->file($sw, ['Content-Type' => 'application/javascript', 'Cache-Control' => 'no-cache']);
});

// Portal Salaf versi web (hasil `flutter build web` di public/app).
// File yang ada (main.dart.js, aset) dilayani langsung oleh web server;
// alamat halaman seperti /app/santri/12 diarahkan ke index.html supaya bisa di-refresh.
Route::get('/app/{halaman?}', function () {
    $index = public_path('app/index.html');
    abort_unless(is_file($index), 404);

    return response()->file($index, ['Cache-Control' => 'no-cache']);
})->where('halaman', '.*');
