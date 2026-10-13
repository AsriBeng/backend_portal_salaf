<?php

use App\Services\TagihanService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('spp:generate {periode? : Bulan dalam format YYYY-MM, bawaan bulan ini}', function (TagihanService $service) {
    $periode = $this->argument('periode') ? Carbon::parse($this->argument('periode').'-01') : now();
    $jumlah = $service->generateSpp($periode);

    $this->info("{$jumlah} tagihan SPP {$periode->translatedFormat('F Y')} dibuat.");
})->purpose('Buat tagihan SPP bulanan untuk semua santri aktif');

// SPP otomatis setiap tanggal 1 pukul 00:05
Schedule::command('spp:generate')->monthlyOn(1, '00:05')->withoutOverlapping();

// Shared hosting: proses antrean notifikasi lewat cron, tanpa worker yang terus berjalan.
// Kunci anti-dobel kedaluwarsa 2 menit (bawaan 24 jam): jika hosting menghentikan proses di tengah jalan,
// antrean tidak ikut macet seharian. Hasil setiap jalan dicatat di storage/logs/antrean.log.
Schedule::command('queue:work --stop-when-empty --max-time=50')
    ->everyMinute()
    ->withoutOverlapping(2)
    ->appendOutputTo(storage_path('logs/antrean.log'));
