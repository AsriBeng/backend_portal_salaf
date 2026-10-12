<?php

use App\Http\Controllers\VerifikasiKartuController;
use Illuminate\Support\Facades\Route;

// Halaman awal: penjelasan sistem verifikasi kartu santri
Route::get('/', [VerifikasiKartuController::class, 'beranda']);

// Halaman verifikasi keaslian kartu santri (tujuan QR code)
Route::get('/kartu/{token}', [VerifikasiKartuController::class, 'show'])->middleware('throttle:60,1');
Route::get('/kartu/{token}/foto', [VerifikasiKartuController::class, 'foto'])->middleware('throttle:60,1');
Route::get('/logo', [VerifikasiKartuController::class, 'logo']);
