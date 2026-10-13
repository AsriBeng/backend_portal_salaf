<?php

use App\Services\TagihanService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schedule;

Artisan::command('spp:generate {periode? : Bulan dalam format YYYY-MM, bawaan bulan ini}', function (TagihanService $service) {
    $periode = $this->argument('periode') ? Carbon::parse($this->argument('periode').'-01') : now();
    $jumlah = $service->generateSpp($periode);

    $this->info("{$jumlah} tagihan SPP {$periode->translatedFormat('F Y')} dibuat.");
})->purpose('Buat tagihan SPP bulanan untuk semua santri aktif');

// SPP otomatis setiap tanggal 1 pukul 00:05
Schedule::command('spp:generate')->monthlyOn(1, '00:05')->withoutOverlapping();

// Shared hosting: proses antrean notifikasi lewat cron, tanpa worker yang terus berjalan.
// Dijalankan di dalam proses schedule:run (bukan proses PHP baru), karena di cPanel proses baru
// sering memakai PHP versi CGI sehingga antrean gagal diam-diam.
// Kunci anti-dobel kedaluwarsa 2 menit (bawaan 24 jam) supaya antrean tidak macet seharian
// jika hosting menghentikan proses di tengah jalan. Hasil dicatat di storage/logs/antrean.log.
Schedule::call(function () {
    Artisan::call('queue:work', ['--stop-when-empty' => true, '--max-time' => 50]);

    $hasil = trim(Artisan::output());
    if ($hasil !== '') {
        File::append(storage_path('logs/antrean.log'), '['.now()->toDateTimeString()."]\n{$hasil}\n");
    }
})
    ->name('proses-antrean-notifikasi')
    ->everyMinute()
    ->withoutOverlapping(2);
